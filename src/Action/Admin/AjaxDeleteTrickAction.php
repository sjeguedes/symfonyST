<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\TrickManager;
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
 * Class AjaxDeleteTrickAction.
 *
 * Manage trick deletion process.
 */
class AjaxDeleteTrickAction
{
    use RouterHelperTrait;
    use UuidHelperTrait;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var TrickManager
     */
    private $trickService;

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
     * AjaxDeleteTrickAction constructor.
     *
     * @param ImageManager      $imageService
     * @param TrickManager      $trickService
     * @param FlashBagInterface $flashBag
     * @param RouterInterface   $router
     * @param Security          $security
     */
    public function __construct(
        ImageManager $imageService,
        TrickManager $trickService,
        FlashBagInterface $flashBag,
        RouterInterface $router,
        Security $security
    ) {
        $this->imageService = $imageService;
        $this->trickService = $trickService;
        $this->flashBag = $flashBag;
        $this->setRouter($router);
        $this->security = $security;
    }

    /**
     *  Manage trick deletion with CSRF token process validation.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/delete-trick/{encodedUuid<\w+>}/{csrfToken<[\w-]+>}"
     * }, name="delete_trick", methods={"DELETE"})
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
        // "delete_trick" must be a unique token id for session storage inside application!
        $token = new CsrfToken('delete_trick', $request->attributes->get('csrfToken'));
        // Action is stopped since token is not allowed!
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException('CSRF Token used for trick deletion is not valid!');
        }
        // Decode trick uuid
        $trickUuid = $this->decode($request->attributes->get('encodedUuid'));
        // Get trick to delete
        /** @var Trick|null $trickToDelete */
        $trickToDelete = $this->trickService->getRepository()->findOneBy(['uuid' => $trickUuid]);
        // Adapt process response parameters with success or error message
        $parameters = $this->manageTrickDeletionResult($trickToDelete);
        // Return JSON response
        return $jsonResponder($parameters['data'], $parameters['statusCode']);
    }

    /**
     * Manage trick deletion result parameters.
     *
     * @param Trick|null $trickToDelete
     *
     * @return array
     *
     * @throws \Exception
     */
    private function manageTrickDeletionResult(?Trick $trickToDelete) : array
    {
        // Error parameters
        $parameters = [
            'data'       => ['status' => 0],
            'statusCode' => 404
        ];
        // Success actions and parameters
        if (!\is_null($trickToDelete)) {
            $data = ['status' => 0];
            // Delete trick
            $isTrickRemoved = $this->trickService->removeTrick($trickToDelete, true);
            if ($isTrickRemoved) {
                // Purge orphaned images physical files
                $this->imageService->purgeOrphanedImagesFiles(
                    ImageUploader::TRICK_IMAGE_DIRECTORY_KEY,
                    $this->imageService->getRepository()->findAll()
                );
                // Trick removal success flash notification
                $message = sprintf(
                        'Requested trick called' . "\n" . '"%s"' . "\n" . 'was successfully deleted!' . "\n" .
                        'Please also note' . "\n" . 'all its associated data do not exist anymore.',
                        $trickToDelete->getName()
                );
                $this->flashBag->add('success', nl2br($message));
                // Success JSON response with redirection and flash bag notification
                $redirectionURI = $this->router->generate('home');
                // Update data
                $data = ['status' => 1, 'notification' => $message, 'redirection' => $redirectionURI];
            }
            // Update parameters
            $parameters = [
                'data'       => $data,
                'statusCode' => 200
            ];
        }
        return $parameters;
    }
}
