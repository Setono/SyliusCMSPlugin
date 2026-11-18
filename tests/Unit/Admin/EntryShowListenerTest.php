<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\EntryShowListener;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EntryShowListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_lets_an_admin_user_see_the_overview_page(): void
    {
        $this->listener(true)(new ResourceControllerEvent(new Page()));

        $this->expectNotToPerformAssertions();
    }

    /**
     * @test
     */
    public function it_denies_anyone_else(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->listener(false)(new ResourceControllerEvent(new Page()));
    }

    /**
     * A content type switched off in setono_sylius_cms.disabled.content_types is hidden from the admin
     *
     * @test
     */
    public function it_has_no_overview_page_of_an_entry_of_a_content_type_switched_off(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->listener(true)(new ResourceControllerEvent(new Recipe()));
    }

    private function listener(bool $admin): EntryShowListener
    {
        $authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')->willReturn($admin);

        return new EntryShowListener(new AdminAccessChecker($authorizationChecker->reveal()), new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ], disabled: ['recipe']));
    }
}
