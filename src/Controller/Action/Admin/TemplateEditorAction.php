<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action\Admin;

use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class TemplateEditorAction
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Request $request, int $id = null): Response
    {
        $content = null;
        if (null !== $id) {
            $template = $this->templateRepository->find($id);
            if (null !== $template) {
                $content = $template->getSource();
            }
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/template/editor.html.twig', [
            'content' => $content,
            'formElement' => $request->query->get('formElement'),
        ]));
    }
}
