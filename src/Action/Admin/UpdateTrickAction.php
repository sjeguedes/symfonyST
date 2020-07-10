<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\UpdateTrickResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class UpdateTrickAction.
 *
 * Manage trick update form.
 */
class UpdateTrickAction
{
    use RouterHelperTrait;
    use UuidHelperTrait;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var VideoManager
     */
    private $videoService;

    /**
     * @var MediaManager
     */
    private $mediaService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var array|FormHandlerInterface[]
     */
    private $formHandlers;

    /**
     * @var Security
     */
    private $security;

    /**
     * UpdateTrickAction constructor.
     *
     * @param TrickManager      $trickService
     * @param ImageManager      $imageService
     * @param VideoManager      $videoService
     * @param MediaManager      $mediaService
     * @param FlashBagInterface $flashBag
     * @param RouterInterface   $router
     * @param array             $formHandlers
     * @param Security          $security
     */
    public function __construct(
        TrickManager $trickService,
        ImageManager $imageService,
        VideoManager $videoService,
        MediaManager $mediaService,
        FlashBagInterface $flashBag,
        array $formHandlers,
        RouterInterface $router,
        Security $security
    ) {
        $this->trickService = $trickService;
        $this->imageService = $imageService;
        $this->videoService = $videoService;
        $this->mediaService = $mediaService;
        $this->flashBag = $flashBag;
        $this->formHandlers = $formHandlers;
        $this->setRouter($router);
        $this->security = $security;
    }

    /**
     *  Show trick update form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/update-trick/{name}"
     * }, name="update_trick")
     *
     * @param RedirectionResponder $redirectionResponder
     * @param UpdateTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, UpdateTrickResponder $responder, Request $request) : Response
    {
        // Get authenticated user
        $authenticatedUser = $this->security->getUser();
        $data = [];
        return $responder($data);
    }
}
