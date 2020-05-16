<?php

declare(strict_types = 1);

namespace App\Utils\Command;

use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Service\Medias\Upload\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class DeleteUnusedImageCommand.
 *
 * Delete any image which is not used in application.
 *
 * Please note this class aims at being used with CRON task.
 * It is useful to remove entities and physical image unattached to application feature.
 * For instance, it can be an image directly uploaded (Trick creation or update process), but not bind due to aborted action!
 *
 * @see https://symfony.com/doc/current/console.html
 */
class DeleteUnusedImageCommand extends Command
{
    use LoggerAwareTrait;

    /**
     * Define call of Command by using an option.
     */
    const COMMAND_CALL_MODE = ['manual' => 0, 'automatic' => 1];

    /**
     * Define a time limit to keep an unused image.
     */
    const IMAGE_USAGE_TIME_LIMIT = 60 * 60 * 24; // 24 Hours expressed in seconds. in seconds to use with timestamps

    /**
     * Define a key to delete unused files in all categories.
     */
    const REMOVE_ALL_KEY = 'all';

    /**
     * @var string the name of the command (the part after "bin/console")
     */
    protected static $defaultName = 'app:delete-unused-image';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var MediaManager
     */
    private $mediaService;

    /**
     * @var array
     */
    private $unusedImageCategories;

    /**
     * @var array
     */
    private $customOptions;

    /**
     * DeleteUnusedImageCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param ImageManager           $imageService
     * @param MediaManager           $mediaService
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ImageManager $imageService,
        MediaManager $mediaService,
        LoggerInterface $logger
    )
    {
        // Define properties to use in class here
        $this->entityManager = $entityManager;
        $this->imageService = $imageService;
        $this->mediaService = $mediaService;
        // Define all existing categories where to delete unused (temporary or not) images
        $this->unusedImageCategories = [
            ucfirst(ImageManager::TRICK_IMAGE_TYPE_KEY), // Unused trick images
            ucfirst(ImageManager::AVATAR_IMAGE_TYPE_KEY), // Unused user avatar images (not used at this time!)
            ucfirst(self::REMOVE_ALL_KEY) // All existing unused images
        ];
        // Use a PSR3 logger
        $this->setLogger($logger);
        // Configure() method is called in parent constructor!
        parent::__construct();
        // IMPORTANT! Here, define custom properties to use in configuration
        // as arguments or options after parent constructor!
        $this->customOptions = $this->getCustomDefinedOptions();
    }

    /**
     * Add custom defined options to configure.
     *
     * @return void
     */
    private function addCustomDefinedOptions() : void
    {
        // Option to define use of Command
        $this->addOption(
            'call',
            null,
            InputOption::VALUE_REQUIRED,
            'Which is the way to call Command?',
            array_search(0, self::COMMAND_CALL_MODE) // "manual" mode
        );
        // Option to select unused image category
        $this->addOption(
            'category',
            null,
            InputOption::VALUE_REQUIRED,
            'Which category(ies) must be used to delete unused image(s)?',
            $this->unusedImageCategories[0] // All categories ("all") are selected: e.g. --category=all, --category=trick, ...
        );
        // Option to set search in temporary file(s) or not
        $this->addOption(
            'temporary',
            null,
            InputOption::VALUE_REQUIRED,
            'Must unused image(s) be found in temporary directory(ies)?',
            false
        );
        $this->addOption(
            'timelimit',
            null,
            InputOption::VALUE_REQUIRED,
            'Which is the delay to use a created image?',
            self::IMAGE_USAGE_TIME_LIMIT
        );
        // Option to set search regexmode or not (particular filename or pattern)
        $this->addOption(
            'regexmode',
            null,
            InputOption::VALUE_REQUIRED,
            'Is search type a pattern or real filename?',
            false
        );
    }

    /**
     * Define configuration for console messages.
     *
     * @see https://symfony.com/doc/current/console/input.html
     *
     * @return void
     */
    protected function configure() : void
    {
        // The short description shown while running "php bin/console list"
        $this->setDescription('Deletes any image present on server which is unused by application');
        // The full command description shown when running the command with the "--help" option
        $this->setHelp('This command allows you remove entirely any unattached image file and its database data ...');
        // Configure custom defined options
        $this->addCustomDefinedOptions();
    }

    /**
     * Delete physically a set of files and remove associated Media and Image entities
     *
     * @param array          $imageFiles
     * @param InputInterface $input
     * @param bool           $isTemporary
     *
     * @see https://stackoverflow.com/questions/29194627/check-if-folder-is-empty-or-not-php
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function deleteFiles(array $imageFiles, InputInterface $input, bool $isTemporary = false) : bool
    {
        // Store state of check process
        $isProcessOK = false;
        // Will store file(s) path(s) to be used after
        $pathArray = [];
        /** @var \SplFileInfo $file */
        foreach ($imageFiles as $index => $file) {
            // Check if time limit is reached to confirm removal or not!
            $isFileOutdated = false;
            $isFileNotCorrectlySaved = false;
            // Image name without extension
            $imageName = preg_replace("/\.{$file->getExtension()}/" , '', $file->getFilename());
            // Query to remove entities (Doctrine flush is integrated!)
            $imageEntity = $this->imageService->findSingleByName($imageName);
            // Image and Media entities must exist, but this avoid issue!
            if (!\is_null($imageEntity)) {
                $isFileOutdated = $this->isUnusedFileOutdated($imageEntity->getCreationDate(), $input);
                if ($isFileOutdated) {
                    $this->mediaService->removeMedia($imageEntity->getMedia());
                    // Normally this is not necessary with cascade option on relationship!
                    $this->imageService->removeImage($imageEntity);
                }
            } else {
                $isFileNotCorrectlySaved = true;
                $this->logger->warning(
                    sprintf(
                        "[trace app snowTricks] DeleteUnusedImageCommand/deleteFiles => error: image \"%s\" " .
                        "was found without being present in database!",
                        $file->getPathname())
                );
            }
            try {
                // Store image directory path to possibly remove it if it is empty!
                $pathArray[] = $file->getPath();
                // Remove physical file (even its data were not stored in database as entities, which is a kind of bug!)
                if ($isFileOutdated || $isFileNotCorrectlySaved) {
                    unlink($file->getPathname());
                }
                // Remove empty temporary directory at the end of loop
                if ((count($imageFiles) - 1 === $index) && $isTemporary) {
                    for ($i = 0; $i < count($pathArray); $i ++) {
                        $path = $pathArray[$i];
                        $pattern = preg_quote(ImageUploader::TEMPORARY_DIRECTORY_NAME, '/');
                        $isTemporaryPath = preg_match("/{$pattern}$/", $path);
                        if ($isTemporaryPath && !$files = glob($path . "/*")) {
                            rmdir($path);
                        }
                    }
                }
                $isProcessOK = true;
             } catch (\Throwable $exception) {
                // Store process error
                $this->logger->error(
                    sprintf("[trace app snowTricks] DeleteUnusedImageCommand/deleteFiles => exception: %s", $exception->getMessage())
                );
                $isProcessOK = false;
            }
        }
        return $isProcessOK;
    }

    /**
     * Execute a particular task.
     *
     * Please note this command deletes unused temporary files created with application.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     *
     * @see https://symfony.com/doc/current/components/console/helpers/questionhelper.html#let-the-user-choose-from-a-list-of-answers
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        // Use Symfony console style
        $ioConsoleStyle = new SymfonyStyle($input, $output);
        // Command is called automatically (e.g. with cron job or with a controller...)
        $key = array_search(1, self::COMMAND_CALL_MODE); // "automatic" mode
        if ($key === $input->getOption('call')) {
            return $this->executeAutomatically($input, $ioConsoleStyle);
        }
        // Command is called manually by default!
        return $this->executeManually($input, $output, $ioConsoleStyle);
    }

    /**
     * Execute command automatically with options.
     *
     * @param InputInterface $input
     * @param SymfonyStyle   $ioConsoleStyle
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function executeAutomatically(InputInterface $input, SymfonyStyle $ioConsoleStyle) : int
    {
        // Get options values
        $categoryPrefix = $input->getOption('category');
        $pattern = $this->getDefaultImageNamePattern($categoryPrefix);
        $isTemporaryFile = false !== $input->getOption('temporary') ? true : false;
        $isRegExMode = false !== $input->getOption('regexmode') ? true : false;
        // Find file(s)
        $imageFiles = $this->findFiles($pattern, $categoryPrefix, $isTemporaryFile, $isRegExMode);
        if (!\is_null($imageFiles)) {
            $isProcessOK = $this->deleteFiles($imageFiles, $input, $isTemporaryFile);
            if ($isProcessOK) {
                $ioConsoleStyle->success(['Good job, process is a success!', 'No more file must be deleted!']);
                return 0;
            } else {
                $ioConsoleStyle->warning(['Error, something wrong happened during process!', 'Please check errors log file.']);
                return 1;
            }
        }
        $ioConsoleStyle->note('Nothing happened. No file matched!');
        return 0;
    }

    /**
     * Execute command manually with arguments.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param SymfonyStyle    $ioConsoleStyle
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function executeManually(InputInterface $input, OutputInterface $output, SymfonyStyle $ioConsoleStyle) : int
    {
        // Output title
        $ioConsoleStyle->title('Delete image(s) (file and database entries)');
        // Output a custom message
        $ioConsoleStyle->section('You are about to remove image(s) reference(s) entirely!');
        // Get User answers to run command
        $answers = $this->getUserManualAnswers($input, $output);
        // Find file(s)
        $imageFiles = $this->findFiles($answers['pattern'], $answers['categoryPrefix'], $answers['isTemporary'], $answers['isRegExMode']);
        // Get search result
        if (\is_null($imageFiles)) {
            $ioConsoleStyle->note('Task was not executed! No file to delete!');
        } else {
            // List image(s)
            $ioConsoleStyle->note('Task is done! File list to delete is:');
            $fileNames = array_map(function ($file) {
                /** @var \SplFileInfo $file */
                return $file->getFilename();
            }, $imageFiles);
            $ioConsoleStyle->listing($fileNames);
            // Get confirmation
            $helper = $this->getHelper('question');
            $question5 = new ChoiceQuestion('Please confirm action:', ['OK', 'Abort'], 0);
            $question5->setErrorMessage('Sorry, action "%s" is unknown.');
            $action = $helper->ask($input, $output, $question5);
            // Delete files
            if ($question5->getChoices()[0] === $action) {
                $isProcessOK = $this->deleteFiles($imageFiles, $input, $answers['isTemporary']);
                if ($isProcessOK) {
                    $ioConsoleStyle->success(['Good job, process is a success!', 'No more file must be deleted!']);
                } else {
                    $ioConsoleStyle->warning(['Error, something wrong happened during process!', 'Please check errors log file.']);
                    return 1;
                }
            // Abort
            } else {
                $ioConsoleStyle->note('Process aborted: no file was deleted!');
            }
        }
        return 0;
    }

    /**
     * Find any corresponding files as regards search.
     *
     * @param string|null $fileName     a base filename or regex pattern
     * @param string      $imageTypeKey
     * @param bool        $isTemporary  a file to find in temporary sub directory
     * @param bool        $isRegExMode  a regex pattern is used as filename
     *
     * @return array|\SplFileInfo[]|null
     *
     * @throws \Exception
     */
    private function findFiles(
        ?string $fileName,
        string $imageTypeKey,
        bool $isTemporary = false,
        bool $isRegExMode = false
    ) : ?array
    {
        $uploadDirectoryKey = $this->getUploadDirectoryKey($imageTypeKey);
        $imageUploader = $this->imageService->getImageUploader();
        // Be careful with this pattern or filename which determines the file(s) to delete!
        $pattern = \is_null($fileName) ? $this->getDefaultImageNamePattern($imageTypeKey) : $fileName;
        // Check if any file matches
        $imageFiles = $imageUploader->checkFileUploadOnServer(
            $pattern,
            $uploadDirectoryKey,
            $isTemporary,
            $isRegExMode
        );
        return $imageFiles;
    }

    /**
     * Get custom defined options (definition list).
     *
     * @return array
     */
    private function getCustomDefinedOptions() : array
    {
        return $this->getDefinition()->getOptions();
    }

    /**
     * Get User answers to run command manually.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return array
     */
    private function getUserManualAnswers(InputInterface $input, OutputInterface $output) : array
    {
        // Choose a category interactively
        $helper = $this->getHelper('question');
        $question1 = new ChoiceQuestion('Please select images category:', $this->unusedImageCategories, 0);
        $question1->setErrorMessage('Sorry, category "%s" is unknown.');
        // Get image category to search, in suggested list
        $categoryPrefix = lcfirst($helper->ask($input, $output, $question1));
        // Get temporary file search mode
        $question2 = new ChoiceQuestion('Does search concern temporary files?', ['Yes', 'No'], false); // false by default
        $question2->setErrorMessage('Sorry, response "%s" is not allowed. Number 0 or 1 is expected!');
        $isTemporary = $question2->getChoices()[1] === $helper->ask($input, $output, $question2) ? false : true;
        // Get regex search mode
        $question3 = new ChoiceQuestion('Is search based on filename partial pattern?', ['Yes', 'No'], false); // false by default
        $question3->setErrorMessage('Sorry, response "%s" is not allowed. Number 0 or 1 is expected!');
        $isRegExMode = $question3->getChoices()[1] === $helper->ask($input, $output, $question3) ? false : true;
        // Get filename/pattern
        $text = $isRegExMode ? 'pattern' : 'filename';
        $question4 = new Question('Please define search string ' . $text . ': ', $this->getDefaultImageNamePattern($categoryPrefix)); // Default IDENTIFIER
        $question4->setMaxAttempts(2);
        $pattern = $helper->ask($input, $output, $question4);
        return [
            'pattern'        => $pattern,
            'categoryPrefix' => $categoryPrefix,
            'isTemporary'    => $isTemporary,
            'isRegExMode'    => $isRegExMode
        ];
    }

    /**
     * Get default pattern depending on image category name.
     *
     * @param string $imageTypeKey an image category name where find unused image(s)
     *
     * @return string
     */
    private function getDefaultImageNamePattern(string $imageTypeKey) : string
    {
        $categoryPrefix = $imageTypeKey;
        // CAUTION: set empty prefix '' for particular case 'all' to avoid issue
        $categoryPrefix = self::REMOVE_ALL_KEY === $categoryPrefix ? '' : $categoryPrefix;
        $identifier = ImageManager::DEFAULT_IMAGE_IDENTIFIER_NAME;
        return $categoryPrefix . $identifier;
    }

    /**
     * Get upload directory key depending on image category name.
     *
     * @param string $imageTypeKey
     *
     * @return string|null
     *
     * @throws \Exception
     */
    private function getUploadDirectoryKey(string $imageTypeKey) : ?string
    {
        $categoryPrefix = $imageTypeKey;
        $uploadDirectoryKey = self::REMOVE_ALL_KEY !== $imageTypeKey ? $this->imageService->getImageDirectoryConstantValue($categoryPrefix) : null;
        return $uploadDirectoryKey;
    }

    /**
     * Check if a file is considered really unused.
     *
     * Please note an arbitrary but pragmatic delay is defined to 24 hours!
     *
     * @param \DateTimeInterface $fileCreationDate
     * @param InputInterface $input
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function isUnusedFileOutdated(\DateTimeInterface $fileCreationDate, InputInterface $input) : bool
    {
        $now = new \DateTime('now');
        $interval = $now->getTimestamp() - $fileCreationDate->getTimestamp();
        // Default time limit is defined by self::IMAGE_USAGE_TIME_LIMIT
        $timeLimitOption = $input->getOption('timelimit');
        return $interval > $timeLimitOption ? true : false;
    }
}
