<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Osynapsy\Net;

class UploadManager
{
    private $documentRoot;
    private $debug = false;

    public function __construct($debug = false)
    {
        $this->documentRoot = filter_input(\INPUT_SERVER, 'DOCUMENT_ROOT');
        $this->debug = $debug;
    }

    public function getUniqueFilename($pathOnDisk)
    {
        if (empty($pathOnDisk)) {
            return false;
        }
        //Se il Path non eiste su disco lo restituisco.
        if (!file_exists($pathOnDisk)) {
            return $pathOnDisk;
        }
        $pathInfo = pathinfo($pathOnDisk);
        $i = 1;
        while (file_exists($pathOnDisk)) {
            $pathOnDisk = $pathInfo['dirname'].'/'.$pathInfo['filename'].'_'.$i.'.'.$pathInfo['extension'];
            $i++;
        }
        return $pathOnDisk;
    }

    private function checkUploadDir($uploadRoot)
    {
        if (empty($uploadRoot)){
            return 'configuration parameters.path-upload is empty';
        }
        $dirToCheck = $this->documentRoot.$uploadRoot;
        if (!is_dir($dirToCheck)) {
            if (!$this->createDir($dirToCheck)) {
                return sprintf('Create %s dir is not possibile', $dirToCheck);
            }
        }

        if (!is_writeable($this->documentRoot.$uploadRoot)) {
            return $this->documentRoot.$uploadRoot.' is not writeable.';
        }
    }

    private function createDir($dir)
    {
        return @mkdir($dir, 0775, true);
    }

    public function saveFile($componentName, $uploadRoot='/upload')
    {
        if (!is_array($_FILES) || !array_key_exists($componentName, $_FILES)){
            return;
        }
        $fileNameFinal = $_FILES[$componentName]['name'];
        $fileNameTemp = $_FILES[$componentName]['tmp_name'];
        $alert = $this->checkUploadDir($uploadRoot);
        if (!empty($alert)) {
            throw new \Exception($alert);
        } elseif(empty($fileNameFinal)) {
            throw new \Exception('Filename is empty for field '.$componentName, 404);
        } elseif (empty($fileNameTemp)) {
            throw new \Exception('Temporary filename is empty for field '.$componentName);
        }
        $pathOnWeb = $uploadRoot.'/'.$fileNameFinal;

        $pathOnDisk = $this->getUniqueFilename($this->documentRoot.$pathOnWeb);
        $pathOnWeb = str_replace($this->documentRoot,'',$pathOnDisk);
        if ($this->debug) {
            throw new \Exception('Path on web : '.$pathOnWeb.' - PathOnDisk :'.$pathOnDisk);
        }
        //Thumbnail path
        if ($pathOnDisk && move_uploaded_file($fileNameTemp, $pathOnDisk)){
            //Inserisco sul db l'immagine
            return $pathOnWeb;
        }
    }
}
