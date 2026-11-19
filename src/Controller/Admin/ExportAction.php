<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Transfer\ExporterInterface;
use Setono\SyliusCMSPlugin\Transfer\ExportPlannerInterface;
use Setono\SyliusCMSPlugin\Transfer\ZipTarget;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The Transfer screen's Download: the export of what the screen's form picks, as a zip holding the export's folder at
 * its root, named by when it was made. With nothing picked, it goes back to the screen. See "Transfer" in
 * docs/spec/admin.md
 */
final readonly class ExportAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ExportRequest $exportRequest,
        private ExportPlannerInterface $planner,
        private ExporterInterface $exporter,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $selection = $this->exportRequest->selection($request);
        if ($selection->isEmpty()) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', 'setono_sylius_cms.export_nothing_picked');
            }

            return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_transfer'));
        }

        $plan = $this->planner->plan($selection);

        $file = sprintf('%s/setono_sylius_cms_export_%s.zip', sys_get_temp_dir(), bin2hex(random_bytes(8)));

        $zip = new ZipTarget($file);

        try {
            $this->exporter->export($plan, $zip);
            $zip->close();
        } catch (\Throwable $e) {
            $zip->discard();

            throw $e;
        }

        $response = new BinaryFileResponse($file, headers: ['Content-Type' => 'application/zip'], autoEtag: false);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, sprintf('cms-export-%s.zip', $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd-His')));
        $response->deleteFileAfterSend();

        return $response;
    }
}
