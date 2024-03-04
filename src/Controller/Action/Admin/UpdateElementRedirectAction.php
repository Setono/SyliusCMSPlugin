<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class UpdateElementRedirectAction
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ManagerRegistry $managerRegistry,
        /** @var array<string, array{classes: array{model: class-string}}> $resources */
        private readonly array $resources,
    ) {
    }

    public function __invoke(Request $response, string $type, string $code): RedirectResponse
    {
        $resource = sprintf('setono_sylius_cms.%s', $type);
        if (!isset($this->resources[$resource])) {
            throw new NotFoundHttpException(sprintf('Resource "%s" not found', $resource));
        }

        $repository = $this->managerRegistry->getRepository($this->resources[$resource]['classes']['model']);

        /** @var ElementInterface|null $element */
        $element = $repository->findOneBy([
            'code' => $code,
        ]);

        if (null === $element) {
            throw new NotFoundHttpException(sprintf(
                'Element with code "%s" not found. Resource: %s',
                $code,
                $resource,
            ));
        }
        Assert::isInstanceOf($element, ElementInterface::class);

        $route = sprintf('setono_sylius_cms_admin_%s_update', $type);

        return new RedirectResponse($this->urlGenerator->generate($route, ['id' => $element->getId()]));
    }
}
