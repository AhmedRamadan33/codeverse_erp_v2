<?php

namespace Modules\Pos\Printing;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Pos\Models\Receipt;

/**
 * Blocks other modules print at the bottom of a POS receipt (e.g. EgyptTax prints the
 * E-Receipt uuid and QR code), so POS does not depend on them.
 */
#[ModuleApi]
class ReceiptPrintExtras
{
    /** @var array<int, Closure(Receipt): (Renderable|Htmlable|string|null)> */
    private array $renderers = [];

    /**
     * @param  Closure(Receipt): (Renderable|Htmlable|string|null)  $renderer  returns trusted HTML
     */
    public function register(Closure $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    public function render(Receipt $receipt): string
    {
        $html = '';
        foreach ($this->renderers as $renderer) {
            $block = $renderer($receipt);
            $html .= match (true) {
                $block instanceof Renderable => $block->render(),
                $block instanceof Htmlable => $block->toHtml(),
                default => (string) $block,
            };
        }

        return $html;
    }
}
