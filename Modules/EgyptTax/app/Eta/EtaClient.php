<?php

namespace Modules\EgyptTax\Eta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaSetting;

/**
 * The ETA E-Receipt API. TLS is always verified; credentials and tokens never appear in
 * exceptions or logs.
 */
class EtaClient
{
    /**
     * Sends built receipts (their exact JSON) in one submission. ETA answers 202 and validates later.
     *
     * @param  string[]  $payloads
     * @return array<string, mixed> the submission response
     *
     * @throws EtaRequestFailed
     */
    public function submit(EtaDevice $device, EtaSetting $eta, array $payloads): array
    {
        $body = '{"receipts":['.implode(',', $payloads).']}';

        $response = $this->authorized($device, $eta, fn (PendingRequest $http) => $http
            ->withBody($body, 'application/json; charset=utf-8')
            ->post($eta->endpoints()['api'].'/api/v1/receiptsubmissions'));

        if ($response->status() !== 202 && ! $response->successful()) {
            throw $this->failure($response);
        }

        return $response->json() ?? [];
    }

    /**
     * The validation result of each receipt in a submission.
     *
     * @return array<string, mixed>
     *
     * @throws EtaRequestFailed
     */
    public function submission(EtaDevice $device, EtaSetting $eta, string $submissionUuid): array
    {
        $response = $this->authorized($device, $eta, fn (PendingRequest $http) => $http
            ->get($eta->endpoints()['api'].'/api/v1/receiptsubmissions/'.rawurlencode($submissionUuid).'/details', ['PageNo' => 1, 'PageSize' => 500]));

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        return $response->json() ?? [];
    }

    /**
     * Runs a request with the device's token, fetching a new token once if ETA refuses it.
     *
     * @param  \Closure(PendingRequest): Response  $send
     */
    private function authorized(EtaDevice $device, EtaSetting $eta, \Closure $send): Response
    {
        $response = $this->call(fn () => $send($this->http()->withToken($this->token($device, $eta))));

        if ($response->status() === 401) {
            Cache::forget($this->tokenKey($device, $eta));
            $response = $this->call(fn () => $send($this->http()->withToken($this->token($device, $eta))));
        }

        return $response;
    }

    private function token(EtaDevice $device, EtaSetting $eta): string
    {
        $key = $this->tokenKey($device, $eta);
        if (is_string($token = Cache::get($key))) {
            return $token;
        }

        [$clientId, $clientSecret] = $device->credentials($eta);
        if (blank($clientId) || blank($clientSecret)) {
            throw new EtaRequestFailed(__('egypttax::receipts.errors.no_credentials'));
        }

        $response = $this->call(fn () => $this->http()
            ->asForm()
            ->withHeaders(array_filter([
                'posserial' => $device->serial,
                'pososversion' => $device->os_version,
                'posmodelframework' => $device->model_framework,
                'presharedkey' => $device->pre_shared_key,
            ], fn ($v) => $v !== null && $v !== ''))
            ->post($eta->endpoints()['identity'].'/connect/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]));

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token)) {
            throw $this->failure($response, __('egypttax::receipts.errors.token'));
        }

        // Tokens last an hour; renew a few minutes early.
        Cache::put($key, $token, max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }

    private function tokenKey(EtaDevice $device, EtaSetting $eta): string
    {
        return 'egypttax.token.'.$device->id.'.'.sha1($eta->environment.'|'.$device->serial.'|'.$device->credentials($eta)[0]);
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout((int) config('egypttax.timeout', 30));
    }

    /**
     * @param  \Closure(): Response  $request
     */
    private function call(\Closure $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new EtaRequestFailed(__('egypttax::receipts.errors.connection'));
        }
    }

    private function failure(Response $response, ?string $prefix = null): EtaRequestFailed
    {
        $error = $response->json('error');
        $detail = match (true) {
            is_array($error) => trim(($error['message'] ?? '').' '.collect($error['details'] ?? [])->pluck('message')->implode(' ')),
            is_string($error) => $error,
            default => '',
        };
        $retryAfter = $response->header('Retry-After');

        return new EtaRequestFailed(
            trim(($prefix ?? __('egypttax::receipts.errors.http', ['status' => $response->status()])).' '.Str::limit($detail, 500)),
            $response->status(),
            is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }
}
