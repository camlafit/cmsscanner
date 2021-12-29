<?php
/**
 * @package    CMSScanner
 * @copyright  Copyright (C) 2014 - 2021 CMS-Garden.org
 * @license    MIT <https://tldrlegal.com/license/mit-license>
 * @link       https://www.cms-garden.org
 */

namespace Cmsgarden\Cmsscanner\Detector\Adapter;

use Cmsgarden\Cmsscanner\Detector\System;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class PrestashopAdapter
 * @package Cmsgarden\Cmsscanner\Detector\Adapter
 *
 * @since   1.0.0
 */
class SpipAdapter implements AdapterInterface
{

    /**
     * Version detection information for Spip
     * @var array
     */
    protected $versions = array(
        array( // 6.x
            'filename' => '/ecrire/inc_version.php',
            'regexp' => '/\s*\$spip_version_branche\s*=\s*[\'"](.*)[\'"]\s*/',
        ),
    );

    /**
     * SPIP has a file called version.php that can be used to search for working installations
     *
     * @param   Finder  $finder  finder instance to append the criteria
     *
     * @return  Finder
     */
    public function appendDetectionCriteria(Finder $finder)
    {
        $finder->name('spip.php');
        $finder->name('spip_loader.php');        
        return $finder;
    }

    /**
     * try to verify a search result and work around some well known false positives
     *
     * @param   SplFileInfo  $file  file to examine
     *
     * @return  bool|System
     */
    public function detectSystem(SplFileInfo $file)
    {
        $fileName = $file->getFilename();
        if ($fileName !== "spip.php" && $fileName !== "spip_loader.php") {
            return false;
        }
        $path = new \SplFileInfo($file->getPathInfo()->getPath());

        // Return result if working
        return new System($this->getName(), $path);
    }

    /**
     * determine version of a SPIP installation within a specified path
     *
     * @param   \SplFileInfo  $path  directory where the system is installed
     *
     * @return  null|string
     */
    public function detectVersion(\SplFileInfo $path)
    {
        foreach ($this->versions as $version) {
            $sysEnvBuilder = $path->getRealPath() . $version['filename'];
            if (!file_exists($sysEnvBuilder)) {
                continue;
            }
            if (!is_readable($sysEnvBuilder)) {
                throw new \RuntimeException(sprintf("Unreadable version information file %s", $sysEnvBuilder));
            }
            if (preg_match($version['regexp'], file_get_contents($sysEnvBuilder), $matches)) {
                if (count($matches) > 1) {
                    return str_replace(',', '.', $matches[1]);
                }
            }
        }
        // this must not happen usually
        return null;
    }

    /**
     * @InheritDoc
     */
    public function detectModules(\SplFileInfo $path)
    {
        // TODO implement this function
        return false;
    }

    /***
     * @return string
     */
    public function getName()
    {
        return 'SPIP';
    }
}
