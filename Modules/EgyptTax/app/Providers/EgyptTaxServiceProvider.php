<?php

namespace Modules\EgyptTax\Providers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\EgyptTax\Console\SubmitCommand;
use Modules\EgyptTax\Console\SyncCommand;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Listeners\IssueEReceipt;
use Modules\EgyptTax\Models\EtaReceipt;
use Modules\EgyptTax\Models\EtaSetting;
use Modules\Pos\Events\PosReceiptCompleted;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Printing\ReceiptPrintExtras;

class EgyptTaxServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'EgyptTax';

    protected string $nameLower = 'egypttax';

    /**
     * @var string[]
     */
    protected array $commands = [
        SubmitCommand::class,
        SyncCommand::class,
    ];

    /**
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(PosReceiptCompleted::class, IssueEReceipt::class);

        // The E-Receipt uuid and QR code at the bottom of the printed POS receipt.
        $this->app->make(ReceiptPrintExtras::class)->register(function (Receipt $receipt) {
            $etaReceipt = EtaReceipt::where('receipt_id', $receipt->id)->whereNotNull('uuid')->whereNull('replaced_by_id')->latest('id')->first();
            if ($etaReceipt === null || $etaReceipt->status === EtaReceiptStatus::Invalid) {
                return null;
            }

            $url = $etaReceipt->shareUrl(EtaSetting::current());
            $svg = (new Writer(new ImageRenderer(new RendererStyle(140, 1), new SvgImageBackEnd)))->writeString($url);

            return view('egypttax::print.receipt-footer', [
                'etaReceipt' => $etaReceipt,
                'qr' => substr($svg, strpos($svg, '<svg')),
            ]);
        });

        $menu = $this->app->make(Menu::class);
        $menu->group('egypttax', 'egypttax::menu.egypttax', 'bi-bank', order: 16);
        $menu->add(new MenuItem('egypttax', 'egypttax::menu.receipts', 'egypttax.receipts.index', 'bi-receipt', 'egypttax.receipts.view', 10));
        $menu->add(new MenuItem('egypttax', 'egypttax::menu.codes', 'egypttax.codes', 'bi-upc', 'egypttax.settings.manage', 20));
        $menu->add(new MenuItem('egypttax', 'egypttax::menu.devices', 'egypttax.devices', 'bi-pc-display', 'egypttax.settings.manage', 30));
        $menu->add(new MenuItem('egypttax', 'egypttax::menu.settings', 'egypttax.settings', 'bi-gear', 'egypttax.settings.manage', 40));
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        // Needs only the scheduler cron that backups already require; no queue worker.
        $schedule->command('egypttax:submit')->everyMinute()->withoutOverlapping();
        $schedule->command('egypttax:sync')->everyFiveMinutes()->withoutOverlapping();
    }
}
