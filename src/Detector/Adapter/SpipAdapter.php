<?php
/**
 * @package    CMSScanner
 * @copyright  Copyright (C) 2014 - 2021 CMS-Garden.org
 * @license    MIT <https://tldrlegal.com/license/mit-license>
 * @link       https://www.cms-garden.org
 */

namespace Cmsgarden\Cmsscanner\Detector\Adapter;

use Cmsgarden\Cmsscanner\Detector\Module;
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
        if ($fileName !== "spip.php") {
            return false;
        }
        $path = new \SplFileInfo($file->getPathInfo()->getPath());

        //In some case plugins could provide also system root file
        //Prevent false positive
        foreach ($this->versions as $version) {
            $sysEnvBuilder = $path->getRealPath() . $version['filename'];
            if (file_exists($sysEnvBuilder)) {
                // Return result if working
                return new System($this->getName(), $path);
            }
        }
        return false;
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
        $modules = array();

        $finder = new Finder();
        $finder->name('meta_cache.php');

        foreach ($finder->in($path->getRealPath()) as $config) {
            $meta_cache = file_get_contents($config->getRealPath());
            $meta_cache = substr($meta_cache, strlen('<' . "?php die ('Acces interdit'); ?" . ">\n"));
            $meta_cache = unserialize($meta_cache);

            $plugins = unserialize($meta_cache['plugin']);

            foreach($plugins as $plugin) {
                if (preg_match('/^php:?/',$plugin['nom'])) {
                    continue; //Ignore virtual module (php feature)
                }
                if (preg_match('/^spip$/i',$plugin['nom'])) {
                    continue; //Ignore SPIP itself (act as a virtual module)
                }
                $modules[] = new Module($plugin['nom'], $config->getRealPath()."/".$plugin['dir_type']."/".$plugin['dir'], $plugin['version']);
            }
        }

        return $modules;
    }

    /***
     * @return string
     */
    public function getName()
    {
        return 'SPIP';
    }
}
