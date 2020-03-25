<?php

declare(strict_types=1);

namespace App\Form\Validator;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\ServiceLayer\ImageManager;
use App\Form\Validator\Constraint\ImageToCropConstraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Class ImageToCropCustomValidator.
 *
 * This class manages a custom constraint validation callback as concerns ImageToCropDTO instance.
 *
 * @see https://symfony.com/doc/current/validation/custom_constraint.html
 * @see https://symfony.com/index.php/doc/4.2/reference/constraints/Callback.html
 */
class ImageToCropConstraintValidator extends ConstraintValidator
{
    /**
     * @var DenormalizerInterface
     */
    private $dataUriDeNormalizer;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var array
     */
    private $uploadDirectories;

    /**
     * ImageToCropCustomValidator constructor.
     *
     * @param DenormalizerInterface $deNormalizer
     * @param ImageManager          $imageService
     *
     * @throws \Exception
     */
    public function __construct(DenormalizerInterface $deNormalizer, ImageManager $imageService)
    {
        // Get a data URI de-normalizer (DataUriNormalizer) for image preview data URI
        $this->dataUriDeNormalizer = $deNormalizer;
        $this->imageService = $imageService;
        // Use image uploader to retrieve upload directories
        $imageUploader = $this->imageService->getImageUploader();
        $this->uploadDirectories = $imageUploader->getUploadDirectories();
    }

    /**
     * Add a constraint validation callback.
     *
     * {@inheritDoc}
     *
     * @return ConstraintViolationListInterface
     *
     * @throws \Exception
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof ImageToCropDTO) {
            throw new \InvalidArgumentException('Object to validate must be an instance of "ImageToCropDTO"!');
        }
        if (!$constraint instanceof ImageToCropConstraint) {
            throw new UnexpectedTypeException($constraint, ImageToCropConstraint::class);
        }
        // Check valid image file field value
        $this->validateImage($this->context);
        // Check valid image crop JSON data field value
        $this->validateCropJSONData($this->context);
        // Check valid image preview data URI (crop view thumb) field value
        $this->validateImagePreviewDataURI($this->context);
        // check valid saved image name (standalone saved image) field value
        $this->validateSavedImageName($this->context);
    }

    /**
     * Apply an intermediate custom validation constraint callback on "image" file property.
     *
     * Please note this is considered not valid if value is null when at the same time, image data URI is also not valid!
     *
     * @param ExecutionContextInterface $context
     * @param mixed|null $payload
     *
     * @return void
     */
    public function validateImage(ExecutionContextInterface $context, $payload = null): void
    {
        // Get current validated object (ImageToCropDTO)
        $object = $context->getObject();
        // Validate manually saved image name and crop JSON data, to be able to validate image which is dependent from these fields
        $context->getValidator()->validate($context, [new Callback([$this, 'validateSavedImageName'])]);
        $context->getValidator()->validate($context, [new Callback([$this, 'validateCropJSONData'])]);
        // Check if there are violations
        $violationsLength = $context->getViolations()->count();
        $isSavedImageNameViolation = false;
        $isCropJSONDataViolation = false;
        if (0 !== $violationsLength) {
            // CAUTION: manual validation above is necessary to add potential violations from image collection to root form violations!
            $violationsList = $context->getViolations();
            // Loop on existing violations to find a potential one which corresponds to manual validation
            foreach ($violationsList as $key => $value) {
                // Each value is a ConstraintViolation instance
                switch ($value->getPropertyPath()) {
                    // A constraint violation exists, so "savedImageName" field is not valid.
                    case $context->getPropertyPath() . '.savedImageName':
                        $isSavedImageNameViolation = true;
                        // Cancel manual validation by removing corresponding ConstraintViolation instance,
                        // not to have violation to be stored twice!
                        $context->getViolations()->remove($key);
                        break;
                    // A constraint violation exists, so "cropJSONData" field is not valid.
                    case $context->getPropertyPath() . '.cropJSONData':
                        $isCropJSONDataViolation = true;
                        // Cancel manual validation by removing corresponding ConstraintViolation instance,
                        // not to have violation to be stored twice!
                        $context->getViolations()->remove($key);
                        break;
                }
            }
        }
        // "image" field must be (re-)filled when "savedImageName" or "cropJSONData" field is not valid!
        if (true === $isSavedImageNameViolation || true === $isCropJSONDataViolation) {
            $context->buildViolation('Please select a new file to validate.')
                ->atPath('image')
                ->addViolation();
        }
        // "image" field can not be null (no uploaded image) when "savedImageName" field is also null.
        // This equals "NotNull" constraint.
        if (\is_null($object->getImage()) && \is_null($object->getSavedImageName())) {
            $addedText = !\is_null($object->getCropJSONData()) ? 'a new file to validate.' : 'an image as expected.';
            $context->buildViolation('Please select ' . $addedText)
                ->atPath('image')
                ->addViolation();
        }
    }

    /**
     * Apply an intermediate custom validation constraint callback on "cropJSONData" property.
     *
     * @param ExecutionContextInterface $context
     * @param mixed|null $payload
     *
     * @return void
     */
    public function validateCropJSONData(ExecutionContextInterface $context, $payload = null): void
    {
        // Get current validated object (ImageToCropDTO)
        $object = $context->getObject();
        // Crop JSON data are tampered by malicious user!
        if (!\is_null($object->getCropJSONData())) {
            // Despite it is useful for Symfony 4.2, this callback would not be necessary with 4.3 new JSON validation constraint.
            json_decode($object->getCropJSONData());
            // string is not a valid JSON!
            if (json_last_error() !== JSON_ERROR_NONE) {
                $context->buildViolation('You are not allowed to tamper image crop data!')
                    ->atPath('cropJSONData')
                    ->addViolation();
            }
        // Replace the "NotNull" constraint to group all cases in this method!
        } else {
            // Validate manually saved image name, to be able to validate image crop JSON data which can not be null if this field is valid!
            $context->getValidator()->validate($context, [new Callback([$this, 'validateSavedImageName'])]);
            // Check if there are violations
            $violationsLength = $context->getViolations()->count();
            $isSavedImageNameViolation = false;
            if (0 !== $violationsLength) {
                // CAUTION: manual validation above is necessary to add potential violations from image collection to root form violations!
                $violationsList = $context->getViolations();
                // Loop on existing violations to find a potential one which corresponds to manual validation
                foreach ($violationsList as $key => $value) {
                    // Each value is a ConstraintViolation instance
                    if ($context->getPropertyPath() . '.savedImageName' === $value->getPropertyPath()) {
                        // A constraint violation exists, so "savedImageName" field is not valid.
                        $isSavedImageNameViolation = true;
                        // Cancel manual validation by removing corresponding ConstraintViolation instance, not to have violation to be stored twice!
                        $context->getViolations()->remove($key);
                        break;
                    }
                }
                // "cropJSONData" field can not be null when "savedImageName" field is valid or not null!
                if ($object->getImage() instanceof UploadedFile || (false === $isSavedImageNameViolation && !\is_null($context->getObject()->getSavedImageName()))) {
                    $context->buildViolation('Image crop JSON data can not be null!')
                        ->atPath('cropJSONData')
                        ->addViolation();
                }
            }
        }
    }

    /**
     * Apply an intermediate custom validation constraint callback on "imagePreviewDataURI" property.
     *
     * @param ExecutionContextInterface $context
     * @param mixed|null                $payload
     *
     * @return void
     *
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function validateImagePreviewDataURI(ExecutionContextInterface $context, $payload = null): void
    {
        // Get current validated object (ImageToCropDTO)
        $object = $context->getObject();
        // Image preview data URI is automatically feed by JavaScript, each time a image is uploaded in corresponding "image to crop" box.
        if (!\is_null($object->getImagePreviewDataURI())) {
            try {
                // Check if a real image is present in base 64 encoded string.
                $this->dataUriDeNormalizer->denormalize($object->getImagePreviewDataURI(), 'SplFileObject');
            } catch (\Exception $exception) {
                // Data was tampered by malicious user!
                $context->buildViolation('You are not allowed to tamper image preview data URI!')
                    ->atPath('imagePreviewDataURI')
                    ->addViolation();
            }
            // Image preview data URI can not be null when a corresponding file was uploaded at the same time!
            // It's really too much to validate "image" field for the preview because the preview is simply a UI data...
        } else {
            if ($object->getImage() instanceof UploadedFile) {
                $context->buildViolation('Image preview data URI can not be null!')
                    ->atPath('imagePreviewDataURI')
                    ->addViolation();
            }
        }
    }

    /**
     * Apply an intermediate custom validation constraint callback on "savedImageName" property.
     *
     * @param ExecutionContextInterface $context
     * @param mixed|null                $payload
     *
     * @return void
     *
     * @throws \Exception
     */
    public function validateSavedImageName(ExecutionContextInterface $context, $payload = null) : void
    {
        // Get current validated object (ImageToCropDTO)
        $object = $context->getObject();
        // Saved image name (which is a kind of identifier) is automatically feed each time a image is uploaded in corresponding "image to crop" box.
        if (!\is_null($object->getSavedImageName())) {
            // Check if a real image which corresponds to saved image name is present on server.
            $isImageFileFound = $this->imageService->getImageUploader()->checkFileUploadOnServer($object->getSavedImageName());
            // Image file may not have been created physically yet, so we check if "cropJSONData" identifier is the same as "savedImageName" value!
            if (!$isImageFileFound && !\is_null($object->getCropJSONData())) {
                $dataObjectFromJSONData = json_decode($object->getCropJSONData());
                if (json_last_error() !== JSON_ERROR_NONE && property_exists(\get_class($dataObjectFromJSONData[0]),'identifier')) {
                    $imageJSONIdentifier = $dataObjectFromJSONData[0]->identifier;
                    // We presume image file will be created on server as a result of direct upload action!
                    $isImageFileFound = $imageJSONIdentifier === $object->getSavedImageName();
                } else {
                    $isImageFileFound = false;
                }
            }
            if (false === $isImageFileFound) {
                // Data was tampered by malicious user!
                $context->buildViolation('You are not allowed to tamper saved image name!')
                    ->atPath('savedImageName')
                    ->addViolation();
            }
        }
        // Saved Image name can not be null when a corresponding file was uploaded at the same time!
        if (\is_null($object->getSavedImageName()) && $object->getImage() instanceof UploadedFile) {
            $context->buildViolation('Saved image name can not be null!')
                ->atPath('savedImageName')
                ->addViolation();
        }
    }
}
