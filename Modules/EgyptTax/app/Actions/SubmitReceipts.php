<?php

namespace Modules\EgyptTax\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Eta\EtaClient;
use Modules\EgyptTax\Eta\EtaErrors;
use Modules\EgyptTax\Eta\EtaRequestFailed;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaReceipt;
use Modules\EgyptTax\Models\EtaSetting;

/**
 * Sends pending E-Receipts per device in chain order. A failed submission is retried later
 * with a growing delay (or the delay ETA asks for); the receipts after it wait their turn.
 */
class SubmitReceipts
{
    public function __construct(
        private readonly EtaClient $client,
        private readonly IssueReceipt $issue,
    ) {}

    /**
     * @return int receipts accepted by ETA
     */
    public function handle(?int $deviceId = null): int
    {
        $eta = EtaSetting::current();
        if (! $eta->isUsable()) {
            return 0;
        }

        $accepted = 0;
        $devices = EtaDevice::where('is_active', true)->when($deviceId, fn ($q) => $q->whereKey($deviceId))->get();

        foreach ($devices as $device) {
            // The scheduler and the after-response send may run at once: one sender per device.
            Cache::lock("egypttax.submit.{$device->id}", 300)->get(function () use ($device, $eta, &$accepted) {
                $accepted += $this->device($device, $eta);
            });
        }

        return $accepted;
    }

    private function device(EtaDevice $device, EtaSetting $eta): int
    {
        // Receipts whose data was missing join the chain once it is complete.
        EtaReceipt::where('device_id', $device->id)->where('status', EtaReceiptStatus::Unbuilt)->orderBy('id')
            ->each(fn (EtaReceipt $r) => $this->issue->build($r));

        $accepted = 0;
        $now = CarbonImmutable::now();

        while (true) {
            $batch = EtaReceipt::where('device_id', $device->id)
                ->where('status', EtaReceiptStatus::Pending)
                ->orderBy('chain_no')
                ->limit((int) config('egypttax.batch_size', 50))
                ->get();

            if ($batch->isEmpty() || $batch->first()->next_attempt_at?->isAfter($now)) {
                return $accepted;
            }

            try {
                $response = $this->client->submit($device, $eta, $batch->pluck('payload')->all());
            } catch (EtaRequestFailed $e) {
                $this->postpone($batch, $e);

                return $accepted;
            }

            $accepted += $this->record($batch, $response);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EtaReceipt>  $batch
     * @param  array<string, mixed>  $response
     */
    private function record($batch, array $response): int
    {
        $submission = $response['submissionId'] ?? $response['submissionUUID'] ?? null;
        $acceptedDocs = collect($response['acceptedDocuments'] ?? [])->keyBy('uuid');
        $rejectedDocs = collect($response['rejectedDocuments'] ?? [])->keyBy('uuid');
        $accepted = 0;

        foreach ($batch as $receipt) {
            $common = ['attempts' => $receipt->attempts + 1, 'next_attempt_at' => null, 'submitted_at' => CarbonImmutable::now()];

            if ($rejected = $rejectedDocs->get($receipt->uuid)) {
                $receipt->update($common + ['status' => EtaReceiptStatus::Invalid, 'errors' => EtaErrors::messages($rejected['error'] ?? $rejected)]);

                continue;
            }

            $receipt->update($common + [
                'status' => EtaReceiptStatus::Submitted,
                'submission_uuid' => $submission,
                'long_id' => $acceptedDocs->get($receipt->uuid)['longId'] ?? null,
                'errors' => null,
            ]);
            $accepted++;
        }

        return $accepted;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EtaReceipt>  $batch
     */
    private function postpone($batch, EtaRequestFailed $e): void
    {
        $attempts = $batch->first()->attempts + 1;
        $minutes = min((int) config('egypttax.retry_max_minutes', 60), (int) config('egypttax.retry_minutes', 1) * 2 ** min($attempts - 1, 16));
        $next = $e->retryAfter !== null ? CarbonImmutable::now()->addSeconds($e->retryAfter) : CarbonImmutable::now()->addMinutes($minutes);

        EtaReceipt::whereKey($batch->modelKeys())->update([
            'attempts' => $attempts,
            'next_attempt_at' => $next,
            'errors' => json_encode([$e->getMessage()], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
