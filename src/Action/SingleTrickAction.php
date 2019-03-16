<?php

declare(strict_types=1);

namespace App\Action;

use App\Domain\Service\MediaTypeManager;
use App\Domain\Service\TrickManager;
use App\Responder\SingleTrickResponder;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SingleTrickAction.
 *
 * Manage single trick page display.
 */
class SingleTrickAction
{
    use LoggerAwareTrait;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeService;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * SingleTrickAction constructor.
     *
     * @param MediaTypeManager $mediaTypeService
     * @param TrickManager     $trickService
     * @param LoggerInterface  $logger
     *
     * @return void
     */
    public function __construct(
        MediaTypeManager $mediaTypeService,
        TrickManager $trickService,
        LoggerInterface $logger
    ) {
        $this->mediaTypeService = $mediaTypeService;
        $this->trickService = $trickService;
        $this->setLogger($logger);
    }

    /**
     * Show homepage with starting list of tricks.
     *
     * @Route("/{_locale}/trick/{slug}-{encodedUuid}", name="single_trick", requirements={"slug":"[\w-]+","encodedUuid":"\w+"})
     *
     * @param SingleTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     */
    public function __invoke(SingleTrickResponder $responder, Request $request) : Response
    {
        // Get registered normal image type (corresponds particular dimensions)
        $trickNormalImageTypeValue = $this->mediaTypeService->getMandatoryDefaultTypes()['trickNormal'];
        $normalImageMediaType = $this->mediaTypeService->findSingleByUniqueType($trickNormalImageTypeValue);
        // Get trick;
        $trick = $this->trickService->findSingleByEncodedUuid($request->attributes->get('encodedUuid'));
        // Check wrong parameters!
        if (\is_null($normalImageMediaType) || \is_null($trick)) {
            $error = \is_null($normalImageMediaType) ? 'Trick normal image type' : 'Trick uuid';
            $this->logger->error("[trace app snowTricks] SingleTrickAction/__invoke => ' . $error . ': null");
            throw new NotFoundHttpException('Trick can not be found or correctly shown! Wrong parameters are used.');
        }
        $data = [
            'mediaError'           => 'Media loading error',
            'mediaTypesValues'     => $this->mediaTypeService->getMandatoryDefaultTypes(),
            'normalImageMediaType' => $normalImageMediaType,
            'videoURLProxyPath'    => $this->trickService->generateURLFromRoute('load_trick_video_url_check', ['url' => '']),
            'trick'                => $trick
        ];
        return $responder($data);
    }
}
