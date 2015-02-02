<?php

class LastAnalyzer extends Analyzer
{
    
    
    public function checkPostGuid()
    {
        $images = $this->getImagesByStatus('init');
        foreach($images as $image) {
            
            if ($this->hasPrefix($image['filepath']) && strpos($image['guid'], '//wp-content')!==false) {
                $guidOk='true';
            } else {
                $guidOk='false';
            }
            $sql = "
                UPDATE dan_images SET
                status='guid',
                guid_ok='".$guidOk."'
                WHERE media_id='" . $image['media_id'] . "'
            ";
            $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    public function checkImageExists()
    {
        $images = $this->getImagesByStatus('guid');
        foreach($images as $image) {
            $prefix = $this->getPrefixFor($image['filepath']);
            $path = substr($image['filepath'], strlen($prefix));
            $rootDir = $this->getRootDir();

            if (file_exists($rootDir.$path.'/'.$image['filename'])) {
                $position = 'ok';
            } else {
                $position = 'not_ok';
            }

            $sql = "
                UPDATE dan_images SET
                status='file_exists',
                position='".$position."'
                WHERE media_id='".$image['media_id']."'
            ";
            $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    function checkFilePosition()
    {
        $images = $this->getImagesByStatus('file_exists');
        foreach($images as $image) {
            if ($image['position']=='ok') {
                $this->query("
                    UPDATE dan_images SET
                    status='file_position'
                    WHERE media_id='" . $image['media_id'] . "'
                ");
                continue;
            }

            $sql = "SELECT * FROM dan_reverse_images WHERE filename='".$image['filename']."'";
            $rimages = $this->query($sql);

            if  (!($rimage = mysql_fetch_assoc($rimages))) {
                $this->createReverseImage($image);
                $rimages = $this->query($sql);
                $rimage = mysql_fetch_assoc($rimages);            
            }

            $pathes = explode("\n",$rimage['pathes']);

            if (count($pathes) >1 ) {
                $sql = "
                    UPDATE dan_images SET
                    status='file_position',
                    position='found_path_more',
                    pathes='".implode("\n",$pathes)."'                
                    WHERE media_id='" . $image['media_id'] . "'
                ";
            } elseif (count($pathes) == 1) {
                $sql = "
                    UPDATE dan_images SET
                    status='file_position',
                    position='found_path',
                    pathes='".$pathes[0]."'
                    WHERE media_id='" . $image['media_id'] . "'
                ";
            } elseif (count($pathes) == 0) {
                $sql = "
                    UPDATE dan_images SET
                    status='file_position',
                    position='not_found'
                    WHERE media_id='" . $image['media_id'] . "'
                ";
            }
            $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    function checkWpAttachedFileIsDirty()
    {
        $images = $this->getImagesByStatus('file_position');
        foreach ($images as $image) {
            $sql = "SELECT * FROM wp_postmeta WHERE meta_key='_wp_attached_file' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);

            $metas = $this->getAsArray($result);
            $meta = $metas[0];

            if (strpos($meta['meta_value'], 'wp-content' )!==false) {
                $bool = 'true';
            } else {
                $bool = 'false';
            }
            $sql = "
                UPDATE dan_images SET
                status='wp_attached_file_is_dirty',
                wp_attached_file_is_dirty='".$bool."',             
                wp_attached_file='".$meta['meta_value']."'                
                WHERE media_id='" . $image['media_id'] . "'
            ";
            $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    function checkWpAttachedFile()
    {
        $images = $this->getImagesByStatus('wp_attached_file_is_dirty');
        foreach ($images as $image) {
            if ($image['wp_attached_file_is_dirty'] == 'true') {
                $value = explode('wp-content/uploads/', $image['wp_attached_file'] );
                $value = $value[1];
            } else {
                $value = $image['wp_attached_file'];
            }

            $guidPart = explode('wp-content/uploads/', $image['guid'] );
            $guidPart = $guidPart[1];


            if ($value == $guidPart) {
                $bool = 'true';
            } else {
    //            $this->writeln($value.' = '.$guidPart);
                $bool = 'false';
            }
            $sql = "
                UPDATE dan_images SET
                status='wp_attached_file',
                wp_attached_file_ok='".$bool."'                
                WHERE media_id='" . $image['media_id'] . "'
            ";
            $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    function checkWpAttachmentMetadataIsDirty()
    {
        $images = $this->getImagesByStatus('wp_attached_file');
        foreach ($images as $image) {
            $sql = "SELECT * FROM wp_postmeta WHERE meta_key='_wp_attachment_metadata' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);

            $metas = $this->getAsArray($result);
            if (!isset($metas[0])) {
                $this->writeln($image['filename'].' has no attachment metadata');
                $bool = 'false';
                $mainfile = '';
            } else {
                $meta = $metas[0];
                $metadata = @unserialize($meta['meta_value']);
                if (!$metadata) {
                    $metadata = preg_replace('!s:(\d+):"(.*?)";!e', "'s:'.strlen('$2').':\"$2\";'", $meta['meta_value']);
                    $metadata = unserialize($metadata);
                }

                if (!isset($metadata['file'])) {
                    $this->writeln($image['filename'].' metadata has no file');
                    $mainfile = '';
                    $bool = 'false';                
                } else {
                    $mainfile = $metadata['file'];

                    if (strpos($mainfile, 'wp-content' )!==false) {
                        $bool = 'true';
                    } else {
                        $bool = 'false';
                    }
                }
            }


            $sql = "
                UPDATE dan_images SET
                status='wp_attachment_metadata_is_dirty',
                wp_attachment_metadata_is_dirty='".$bool."',             
                wp_attachment_metadata='".$mainfile."'                
                WHERE media_id='" . $image['media_id'] . "'
            ";
            $this->query($sql);
            
            $this->checkTime();
        }
    }

    
    function checkWpAttachmentMetadata()
    {
        $images = $this->getImagesByStatus('wp_attachment_metadata_is_dirty');
        foreach ($images as $image) {
            if ($image['wp_attachment_metadata_is_dirty'] == 'true') {
                $value = explode('wp-content/uploads/', $image['wp_attachment_metadata'] );
                $value = $value[1];
            } else {
                $value = $image['wp_attachment_metadata'];
            }

            $guidPart = explode('wp-content/uploads/', $image['guid'] );
            $guidPart = $guidPart[1];


            if (!$value) {
                $bool = 'true';
            } elseif ($value == $guidPart) {
                $bool = 'true';
            } else {
                $this->writeln($value.' = '.$guidPart);
                $bool = 'false';
            }
            $sql = "
                UPDATE dan_images SET
                status='wp_attachment_metadata',
                wp_attachment_metadata_ok='".$bool."'                
                WHERE media_id='" . $image['media_id'] . "'
            ";
            $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    public function reset()
    {
        $this->removeAllData();
    }
    
    public function execute()
    {
        $this->scanPosts();
        
        $this->checkPostGuid();
        $this->fixGuidNotOk();
        
        $this->checkImageExists();
        $this->checkFilePosition();
        $this->fixPositionNotOkFoundPath();
        $this->fixPositionNotOkFoundPathMore();        

        $this->checkWpAttachedFileIsDirty();
        $this->fixWpAttachedFileIsDirty();
        
        $this->checkWpAttachedFile();
        $this->fixWpAttachedFileNotOk();
        
        $this->checkWpAttachmentMetadataIsDirty();
        $this->fixWpAttachmentMetadataIsDirty();
        
        $this->checkWpAttachmentMetadata();
        $this->fixWpAttachmentMetadataNotOk();
    }
    
    
}