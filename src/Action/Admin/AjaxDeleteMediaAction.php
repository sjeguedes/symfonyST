<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Image;
use App\Domain\Entity\Video;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Responder\Json\JsonResponder;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class AjaxDeleteMediaAction.
 *
 * Manage image or video media deletion process.
 */
class AjaxDeleteMediaAction
{
    use RouterHelperTrait;
    use UuidHelperTrait;

    /**
     * Define use of HTTP referer.
     */
    const ALLOWED_HTTP_REFERER = true;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var VideoManager
     */
    private $videoService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Security
     */
    private $security;

    /**
     * AjaxDeleteMediaAction constructor.
     *
     * @param ImageManager         $imageService
     * @param videoManager         $videoService
     * @param FlashBagInterface    $flashBag
     * @param RouterInterface      $router
     * @param Security             $security
     */
    public function __construct(
        ImageManager $imageService,
        videoManager $videoService,
        FlashBagInterface $flashBag,
        RouterInterface $router,
        Security $security
    ) {
        $this->imageService = $imageService;
        $this->videoService = $videoService;
        $this->flashBag = $flashBag;
        $this->setRouter($router);
        $this->security = $security;
    }

    /**
     *  Manage image or video media deletion with CSRF token process validation.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/delete-media/{mediaType<image|video>}/{encodedUuid<\w+>}/{csrfToken<[\w-]+>}"
     * }, name="delete_media", methods={"DELETE"})
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param JsonResponder             $jsonResponder
     * @param Request                   $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(CsrfTokenManagerInterface $csrfTokenManager, JsonResponder $jsonResponder, Request $request) : Response
    {
        // Filter AJAX request
        if (!$request->isXmlHttpRequest()) {
            throw new AccessDeniedException('Access is not allowed without AJAX request!');
        }
        // "delete_image" or "delete_video" must be a unique token id for session storage inside application!
        $mediaType = $request->attributes->get('mediaType');
        $token = new CsrfToken('delete_' . $mediaType, $request->attributes->get('csrfToken'));
        // Action is stopped since token is not allowed!
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException("CSRF Token used for $mediaType deletion is not valid!");
        }
        // Decode media uuid
        $mediaUuid = $this->decode($request->attributes->get('encodedUuid'));
        // Get media to delete
        switch ($mediaType) {
            case 'image':
                /** @var Image|null $trickToDelete */
                $mediaToDelete = $this->imageService->getRepository()->findOneBy(['uuid' => $mediaUuid]);
                break;
            case 'video':
                /** @var Video|null $trickToDelete */
                $mediaToDelete = $this->videoService->getRepository()->findOneBy(['uuid' => $mediaUuid]);
                break;
            default:
                $mediaToDelete = null;
        }
        // Adapt process response parameters with success or error message
        $parameters = $this->manageMediaDeletionResult($mediaToDelete, $mediaType, $request);
        // Return JSON response
        return $jsonResponder($parameters['data'], $parameters['statusCode']);
    }

    /**
     * Manage trick deletion result parameters.
     *
     * @param object|null $mediaToDelete
     * @param string      $mediaType
     * @param  Request    $request
     *
     * @return array
     *
     * @throws \Exception
     */
    private function manageMediaDeletionResult(?object $mediaToDelete, string $mediaType, Request $request) : array
    {
        // Error parameters
        $parameters = [
            'data'       => ['status' => 0],
            'statusCode' => 404
        ];
        // Success actions and parameters
        if (!\is_null($mediaToDelete)) {
            $data = ['status' => 0];
            // Delete media
            $isMediaRemoved =$this->manageMediaRemoval($mediaToDelete, $mediaType);
            // Success information and data
            if ($isMediaRemoved) {
                // Media removal success flash notification
                $message = "Requested $mediaType" . "\n" . 'was successfully deleted!' . "\n" .
                           'Please also note' . "\n" . 'all its associated data do not exist anymore.';
                $this->flashBag->add('success', $message);
                // Update data
                switch ($httpReferer = $request->server->get('HTTP_REFERER')) {
                    case 1 === preg_match('/update-trick/', $httpReferer):
                        $data = ['status' => 1, 'notification' => $message];
                        break;
                    default:
                        // Make redirection or not depending on configuration
                        $data = ['status' => 1, 'notification' => $message];
                        !self::ALLOWED_HTTP_REFERER ?: $data['redirection'] = $httpReferer;
                }
            }
            // Update parameters
            $parameters = [
                'data'       => $data,
                'statusCode' => 200
            ];
        }
        return $parameters;
    }

    /**
     * Try media removal and return state.
     *
     * @param object $mediaToDelete
     * @param string $mediaType
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function manageMediaRemoval(object $mediaToDelete, string $mediaType) : bool
    {
        // Delete media
        switch ($mediaType) {
            case 'image':
                $isMediaRemoved = false;
                /** @var Image $mediaToDelete */
                if (!\is_null($foundEntities = $this->imageService->getImageWithIdenticalName($mediaToDelete))) {
                    foreach ($foundEntities as $imageEntity) {
                        // Remove Image, MediaSource and Media entities thanks to cascade
                        $isMediaRemoved = $this->imageService->removeImage($imageEntity, false)
                            ? true : $isMediaRemoved;
                    }
                    // CAUTION! Save removal globally before purging file(s) to make it work!
                    $this->imageService->getEntityManager()->flush();
                    // Purge orphaned images physical files if media to delete was of type 'image'.
                    !$isMediaRemoved ?: $this->imageService->purgeOrphanedImagesFiles(
                        ImageUploader::TRICK_IMAGE_DIRECTORY_KEY,
                        $this->imageService->getRepository()->findAll()
                    );
                }
                return $isMediaRemoved;
            case 'video':
                // Save removal for one video only
                /** @var Video $mediaToDelete */
                return $isMediaRemoved = $this->videoService->removeVideo($mediaToDelete, true);
            default:
                return $isMediaRemoved = false;
        }
    }
}
