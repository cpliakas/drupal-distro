<?php

namespace Drupal\Distro\Console;

use Guzzle\Http\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class NewCommand extends Command
{
    const UPDATES_URL = 'http://updates.drupal.org/release-history/drupal';

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * @param \Symfony\Component\Filesystem\Filesystem $fs
     * @param string|null $name
     */
    public function __construct(Filesystem $fs, $name = null)
    {
        $this->templateDir = __DIR__ . '/../../../../template/';
        parent::__construct($name);
        $this->fs = $fs;
    }

    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Drupal distro')
            ->addArgument(
               'profile',
               InputArgument::REQUIRED,
               'The machine name of the installation profile.'
            )
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'The directory that the Drupal distro will be created in.'
            )
            ->addOption(
               'site-name',
                null,
               InputOption::VALUE_REQUIRED,
               'The site name as configured in the Drupal application'
            )
            ->addOption(
               'profile-name',
                null,
               InputOption::VALUE_REQUIRED,
               'The human readable name of the profile'
            )
            ->addOption(
               'profile-description',
                null,
               InputOption::VALUE_REQUIRED,
               'The human readable description of the profile'
            )
            ->addOption(
               'core-version',
                null,
               InputOption::VALUE_REQUIRED,
               'The longer description of the profile'
            )
            ->addOption(
               'git-repo',
                null,
               InputOption::VALUE_REQUIRED,
               'The URL of the Git repository hosting the distro'
            )
        ;
    }

    /**
     * @{inheritdoc}
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $profile     = $input->getArgument('profile');
        $dir         = $input->getArgument('directory') ?: './' . $profile;
        $siteName    = $input->getOption('site-name') ?: $profile;
        $profileName = $input->getOption('profile-name') ?: $profile;
        $profileDesc = $input->getOption('profile-description') ?: $profile;
        $gitRepo     = $input->getOption('git-repo') ?: 'http://git.drupal.org/project/' . $profile . '.git';
        $coreVersion = $input->getOption('core-version') ?: '7';
        $coreBranch  = $coreVersion . '.x';

        if (!is_dir($this->templateDir . '/' . $coreBranch)) {
            throw new \RuntimeException('Core version not valid: ' . $coreVersion);
        }

        $replacements = array(
          '{{ site.name }}'           => $siteName,
          '{{ profile }}'             => $profile,
          '{{ profile.name }}'        => $profileName,
          '{{ profile.description }}' => $profileDesc,
          '{{ drupal.version }}'      => $this->getDrupalVersion($coreBranch),
        );

        $filenames = array(
            '.editorconfig',
            '.gitignore',
            '.travis.yml',
            'build.xml',
            'build.properties.dist',
            'behat.yml',
            'build-example.make',
            'test/composer.json',
            'test/features/bootstrap/FeatureContext.php',
            'test/features/test.feature',
            'drupal-org-core.make',
            'drupal-org.make',
        );

        if ('7' == $coreVersion) {
            $filenames[] = $coreBranch . '/example.info';
            $filenames[] = $coreBranch . '/example.install';
            $filenames[] = $coreBranch . '/example.profile';
        }

        $this->mkdir($dir . '/' . $coreBranch);
        $this->mkdir($dir . '/test/features/bootstrap');
        $this->mkdir($dir . '/test');
        $this->mkdir($dir . '/test/features');
        $this->mkdir($dir . '/test/features/bootstrap');

        foreach ($filenames as $filename) {
            $this->copy($filename, $dir, $replacements);
        }

        // Rename the build-example.make file.
        $this->fs->rename($dir . '/build-example.make', $dir . '/build-' . $profile . '.make');

        // Move the profile files and remove the stub dir.
        $flags = \FilesystemIterator::SKIP_DOTS;
        $profileFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir . '/' . $coreBranch, $flags),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $pattern = '@' . preg_quote($coreBranch, '@') . '/example(\\.[a-z]+)$@';
        $replacement = $profileName . '\\1';
        foreach ($profileFiles as $profileOrigin) {
            $profileTarget = preg_replace($pattern, $replacement, $profileOrigin);
            $this->fs->rename($profileOrigin, $profileTarget);
        }

        $this->fs->remove($dir . '/' . $coreBranch);
    }

    /**
     * @param string $dir
     */
    public function mkdir($dir)
    {
        return $this->fs->mkdir($dir, 0755);
    }

    /**
     * Copies a file from the template to the destination directory, replacing
     * all of the template variables.
     *
     * @param string $filename
     * @param string $dir
     * @param array $replacements
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    public function copy($filename, $dir, array $replacements = array(), $newname = null)
    {
        $filepath = __DIR__ . '/../../../../template/' . $filename;
        if (!is_file($filepath)) {
            throw new \RuntimeException('File not found: ' . $filename);
        }

        $filedata = $this->replace($replacements, file_get_contents($filepath));
        $this->write($dir . '/' . $filename, $filedata);
    }

    /**
     * Writes data to a file.
     *
     * @param string $filepath
     * @param string $filedata
     */
    public function write($filepath, $filedata)
    {
        $this->fs->touch($filepath);
        $this->fs->chmod($filepath, 0644);
        file_put_contents($filepath, $filedata);
    }

    /**
     * Expands variables in a string.
     *
     * @param array $replacements
     * @param string $subject
     *
     * @return string
     */
    public function replace(array $replacements, $subject)
    {
        $search  = array_keys($replacements);
        $replace = array_values($replacements);
        return str_replace($search, $replace, $subject);
    }

    /**
     * Returns the latest Drupal version.
     *
     * @param string $coreBranch e.g. 7.x, 8.x, etc.
     *
     * @return string
     */
    public function getDrupalVersion($coreBranch)
    {
        $client = new Client();
        $xml = $client->get(self::UPDATES_URL . '/' . $coreBranch)->send()->xml();
        $release = $xml->xpath('/project/releases/release[1]/version');
        return (string) $release[0];
    }
}
