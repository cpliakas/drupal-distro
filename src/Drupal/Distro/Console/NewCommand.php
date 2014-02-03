<?php

namespace Drupal\Distro\Console;

use GitWrapper\GitWrapper;
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
     * @var \Guzzle\Http\Client
     */
    private $httpClient;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    private $fs;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * {@inheritdoc}
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->templateDir = __DIR__ . '/../../../../template';
    }

    /**
     * @param \Guzzle\Http\Client $client
     *
     * @return \Drupal\Distro\Console\NewCommand
     */
    public function setHttpClient(Client $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @return \Guzzle\Http\Client
     */
    public function getHttpClient()
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new Client();
        }
        return $this->httpClient;
    }

    /**
     * @param \Symfony\Component\Filesystem\Filesystem $fs
     *
     * @return \Drupal\Distro\Console\NewCommand
     */
    public function setFilesystem(Filesystem $fs)
    {
        $this->fs = $fs;
        return $this;
    }

    /**
     * @return \Symfony\Component\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        if (!isset($this->fs)) {
            $this->fs = new Filesystem();
        }
        return $this->fs;
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
               'git-url',
                null,
               InputOption::VALUE_REQUIRED,
               'The URL of the Git repository hosting the distro'
            )
            ->addOption(
               'git-binary',
                null,
               InputOption::VALUE_REQUIRED,
               'The path to the Git binary'
            )
            ->addOption(
               'no-repo',
                null,
               InputOption::VALUE_NONE,
               'Don\'t create the Git repository'
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
        $wrapper     = new GitWrapper($input->getOption('git-binary'));
        $profile     = $input->getArgument('profile');
        $dir         = $input->getArgument('directory') ?: './' . $profile;
        $profileName = $input->getOption('profile-name') ?: $profile;
        $profileDesc = $input->getOption('profile-description') ?: $profile;
        $siteName    = $input->getOption('site-name') ?: $profileName;
        $gitUrl      = $input->getOption('git-url') ?: 'http://git.drupal.org/project/' . $profile . '.git';
        $coreVersion = $input->getOption('core-version') ?: '7';
        $coreBranch  = $coreVersion . '.x';

        if (!is_dir($this->templateDir . '/' . $coreBranch)) {
            throw new \RuntimeException('Core version not valid: ' . $coreVersion);
        }

        if (preg_match('/[^a-zA-Z0-9_]/', $profile)) {
            throw new \RuntimeException('Profile name must only contain letters, numbers, and underscores');
        }

        $replacements = array(
            '{{ drupal.version }}'      => $this->getLatestDrupalVersion($coreBranch),
            '{{ git.url }}'             => $gitUrl,
            '{{ profile }}'             => $profile,
            '{{ profile.name }}'        => $profileName,
            '{{ profile.description }}' => $profileDesc,
            '{{ site.name }}'           => $siteName,
        );

        $filenames = array(
            '.editorconfig',
            '.gitignore',
            '.travis.yml',
            'build.xml',
            'build.properties.dist',
            'behat.yml',
            'build-example.make',
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
            $this->copyFile($filename, $dir, $replacements);
        }

        $fs = $this->getFilesystem();

        // Rename the build-example.make file.
        $fs->rename($dir . '/build-example.make', $dir . '/build-' . $profile . '.make');

        // Move the profile files and remove the stub dir.
        $flags = \FilesystemIterator::SKIP_DOTS;
        $profileFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir . '/' . $coreBranch, $flags),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $pattern = '@' . preg_quote($coreBranch, '@') . '/example(\\.[a-z]+)$@';
        $replacement = $profile . '\\1';
        foreach ($profileFiles as $profileOrigin) {
            $profileTarget = preg_replace($pattern, $replacement, $profileOrigin);
            $fs->rename($profileOrigin, $profileTarget);
        }

        $fs->remove($dir . '/' . $coreBranch);

        if (!$input->getOption('no-repo')) {
            $git = $wrapper->init($dir);
            $git->add('*', array('A' => true));
            $git->commit('first commit');
            $git->remote('add', 'origin', $gitUrl);
        }
    }

    /**
     * @param string $dir
     */
    public function mkdir($dir)
    {
        return $this->getFilesystem()->mkdir($dir, 0755);
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
    public function copyFile($filename, $dir, array $replacements = array(), $newname = null)
    {
        $filepath = $this->templateDir . '/' . $filename;
        if (!is_file($filepath)) {
            throw new \RuntimeException('File not found: ' . $filename);
        }

        $filedata = $this->replaceVariables($replacements, file_get_contents($filepath));
        $this->getFilesystem()->dumpFile($dir . '/' . $filename, $filedata, 0644);
    }

    /**
     * Expands variables in a string.
     *
     * @param array $replacements
     * @param string $subject
     *
     * @return string
     */
    public function replaceVariables(array $replacements, $subject)
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
     *
     * @throws \UnexpectedValueException
     */
    public function getLatestDrupalVersion($coreBranch)
    {
        // Parse the latest Drupal version from drupal.org's XML feed.
        $release = $this
            ->getHttpClient()
            ->get(self::UPDATES_URL . '/' . $coreBranch)
            ->send()
            ->xml()
            ->xpath('/project/releases/release[1]/version')
        ;

        if (!isset($release[0])) {
            throw new \UnexpectedValueException('Invalid response: Latest Drupal release not found.');
        }

        return (string) $release[0];
    }
}
