<?php

class Analyzer extends BaseAnalyzer
{

    function removeAllData()
    {
        $sql = "DELETE FROM dan_images";
        $this->query($sql);
//        $sql = "DELETE FROM dan_reverse_images";
//        $this->query($sql);
    }

    function createImage($post)
    {
        $pi = pathinfo($post['guid']);

        $filename = $pi['basename'];
        $path = $pi['dirname'];


        $sql = "
                INSERT INTO dan_images SET
                media_id='" . $post['ID'] . "',
                status='init',
                filename='" . $filename . "',
                guid='" . $post['guid'] . "',
                filepath='" . $path . "'
        ";

        $this->query($sql);
    }
    
    function createReverseImage($image)
    {
        $rootDir = $this->getRootDir();
        $pathes = array();
        $this->searchFile($rootDir.'/wp-content/uploads', $image['filename'], $pathes);

        $sql = "
            INSERT INTO dan_reverse_images SET
            filename='".$image['filename']."',
            pathes='".implode("\n",$pathes)."',
            num='".count($pathes)."'
        ";
        $this->query($sql);
    }

    function checkPost($post)
    {
        $sql = "SELECT * FROM dan_images WHERE media_id='" . $post['ID'] . "'";
        $images = $this->query($sql);

        if (!($image = mysql_fetch_assoc($images))) {
            $this->createImage($post);
        }
    }

    function scanPosts()
    {
        $sql = "SELECT * FROM wp_posts WHERE post_type='attachment' ORDER BY ID desc";
        $posts = $this->query($sql);

        if ($posts) {
            while ($post = mysql_fetch_assoc($posts)) {
                $this->checkPost($post);
                $this->checkTime();
            }
        }
    }
    


    public function getImagesByStatus($status)
    {
        $sql = "SELECT * FROM dan_images WHERE status='" . $status . "'";
        $result = $this->query($sql);

        return $this->getAsArray($result);
    }

    
    public function fixGuidNotOk()
    {
        $sql = "
            SELECT * FROM dan_images
            WHERE guid_ok='false'
        ";
        $images = $this->getAsArray($this->query($sql));
        
        foreach($images as $image) {
            $guid = $image['guid'];
            $filepath = $image['filepath'];
            $guid = strtr($guid, array(
                '//wp-content' => '/wp-content',
            ));
            
            $filepath = strtr($filepath, array(
                '//wp-content' => '/wp-content',
            ));
            
            $sql = "
                    UPDATE wp_posts SET
                    guid='".$guid."'
                    WHERE ID='".$image['media_id']."'";
            
            $result = $this->query($sql);
            
            $sql = "
                    UPDATE dan_images SET
                    filepath='".$filepath."',
                    guid='".$guid."',
                    guid_ok='true'
                    WHERE id='".$image['id']."'";
            $result = $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    
    public function fixWpAttachedFileIsDirty()
    {
        $sql = "
            SELECT * FROM dan_images
            WHERE guid_ok='true'
            AND position='ok'
            AND wp_attached_file_is_dirty='true'
        ";
        $images = $this->getAsArray($this->query($sql));
        
        foreach($images as $image) {
            $value = explode('wp-content/uploads/', $image['wp_attached_file'] );
            $value = $value[1];
            
//            $this->writeln($value);
            $sql = "
                    UPDATE wp_postmeta SET
                    meta_value='".$value."'
                    WHERE meta_key='_wp_attached_file' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);
            $sql = "
                    UPDATE dan_images SET
                    wp_attached_file_is_dirty='false',
                    wp_attached_file='".$value."'
                    WHERE id='".$image['id']."'";
            $result = $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    public function fixWpAttachmentMetadataIsDirty()
    {
        $sql = "
            SELECT * FROM dan_images
            WHERE guid_ok='true'
            AND position='ok'
            AND wp_attachment_metadata_is_dirty='true'
        ";
        $images = $this->getAsArray($this->query($sql));
        
        foreach($images as $image) {
            $value = explode('wp-content/uploads/', $image['wp_attachment_metadata'] );
            $value = $value[1];
            
            $sql = "SELECT * FROM wp_postmeta WHERE meta_key='_wp_attachment_metadata' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);

            $metas = $this->getAsArray($result);
            $meta = $metas[0];
            $metadata = unserialize($meta['meta_value']);

            $metadata['file'] = $value;
            
//            $this->writeln($value);
            
            $sql = "
                    UPDATE wp_postmeta SET
                    meta_value='".serialize($metadata)."'
                    WHERE meta_key='_wp_attachment_metadata' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);
            $sql = "
                    UPDATE dan_images SET
                    wp_attachment_metadata_is_dirty='false',
                    wp_attachment_metadata='".$value."'
                    WHERE id='".$image['id']."'";
            $result = $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    public function fixWpAttachedFileNotOk()
    {
        $sql = "
            SELECT * FROM dan_images
            WHERE guid_ok='true'
            AND position='ok'
            AND wp_attached_file_is_dirty='false'
            AND wp_attached_file_ok='false'
        ";
        $images = $this->getAsArray($this->query($sql));
        
        foreach($images as $image) {
            $value = explode('wp-content/uploads/',$image['filepath']);
            $value = $value[1];
            $value = $value.'/'.$image['filename'];
            
            $sql = "
                    UPDATE wp_postmeta SET
                    meta_value='".$value."'
                    WHERE meta_key='_wp_attached_file' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);
            $sql = "
                    UPDATE dan_images SET
                    wp_attached_file_ok='true',
                    wp_attached_file='".$value."'
                    WHERE id='".$image['id']."'";
            $result = $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    public function fixWpAttachmentMetadataNotOk()
    {
        $sql = "
            SELECT * FROM dan_images
            WHERE guid_ok='true'
            AND position='ok'
            AND wp_attachment_metadata_is_dirty='false'
            AND wp_attachment_metadata_ok='false'
        ";
        $images = $this->getAsArray($this->query($sql));
        
        foreach($images as $image) {
            $value = explode('wp-content/uploads/', $image['filepath']);
            $value = $value[1];
            $value = $value.'/'.$image['filename'];
            
            $sql = "SELECT * FROM wp_postmeta WHERE meta_key='_wp_attachment_metadata' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);

            $metas = $this->getAsArray($result);
            $meta = $metas[0];
            $metadata = unserialize($meta['meta_value']);

            $metadata['file'] = $value;
            
            $sql = "
                    UPDATE wp_postmeta SET
                    meta_value='".serialize($metadata)."'
                    WHERE meta_key='_wp_attachment_metadata' AND post_id='".$image['media_id']."'";
            $result = $this->query($sql);
            $sql = "
                    UPDATE dan_images SET
                    wp_attachment_metadata_ok='true',
                    wp_attachment_metadata='".$value."'
                    WHERE id='".$image['id']."'";
            $result = $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    public function fixPositionNotOkFoundPath()
    {
        $sql = "
            SELECT * FROM dan_images
            WHERE guid_ok='true'
            AND position='found_path'
        ";
        $images = $this->getAsArray($this->query($sql));
        
        foreach($images as $image) {
            $path = $image['pathes'];
            $path = explode('wp-content/uploads/',$path);
            $path = $path[1];
            $filename = $image['filename'];
            $filepath = explode('wp-content/uploads/',$image['filepath']);
            $filepath = $filepath[0].'wp-content/uploads/'.$path;
            
//            $this->writeln($filepath.'   '.$filename);
//            $this->writeln($filepath.'/'.$filename);
            
            $guid = $filepath.'/'.$filename;
            
            $sql = "
                    UPDATE wp_posts SET
                    guid='".$guid."'
                    WHERE ID='".$image['media_id']."'";
            
            
            $result = $this->query($sql);
            
            $sql = "
                    UPDATE dan_images SET
                    guid='".$guid."',
                    pathes='',
                    filepath='".$filepath."',
                    filename='".$filename."',
                    position='ok'
                    WHERE id='".$image['id']."'";
            $result = $this->query($sql);
            
            $this->checkTime();
        }
    }
    
    
    public function fixPositionNotOkFoundPathMore()
    {
        
        $rootDir = $this->getRootDir();
        $sql = "
            SELECT filename FROM dan_images
            WHERE position='found_path_more'
            GROUP BY filename
        ";
        $filenames = $this->getAsArray($this->query($sql));
        
        foreach($filenames as $filename) {
            $filename = $filename['filename'];
            $sql = "
                SELECT * FROM dan_images
                WHERE position='found_path_more'
                AND filename='".$filename."'
            ";
            $images = $this->getAsArray($this->query($sql));
            foreach($images as $i => $image) {
                $newFilename = $filename;
                if ($i==0) {
                    $pathes = explode("\n", $image['pathes']);
                    foreach($pathes as $path) {
                        if (strpos($path, 'custom'!==false)) {  //I used https://wordpress.org/plugins/custom-upload-dir/ and my custom dir was /wp-contents/uploads/custom/%year%/%month%/%day%
                            break;
                        }
                    }
                    if (!$path) {
                        $path = $pathes[0];
                    }
                }
                
                $path = explode('wp-content/uploads/',$path);
                if (isset($path[1])) {
                    $path = $path[1];
                } else {
                    $path = $path[0];
                }
                
                if ($i!=0) {
                    $pi = pathinfo($newFilename);
                    $newFilename = $pi['filename'].$i.'.'.$pi['extension'];
                    copy($rootDir.'/wp-content/uploads/'.$path.'/'.$filename, $rootDir.'/wp-content/uploads/'.$path.'/'.$newFilename);
                }
                
                $filepath = explode('wp-content/uploads/',$image['filepath']);
                $filepath = $filepath[0].'wp-content/uploads/'.$path;
                
                $guid = $filepath.'/'.$newFilename;
                
                $this->writeln($guid);
                
                $sql = "
                        UPDATE wp_posts SET
                        guid='".$guid."'
                        WHERE ID='".$image['media_id']."'";

                $result = $this->query($sql);

                $sql = "
                        UPDATE dan_images SET
                        pathes='',
                        guid='".$guid."',
                        filepath='".$filepath."',
                        filename='".$newFilename."',
                        position='ok'
                        WHERE id='".$image['id']."'";
                $result = $this->query($sql);
                
            }
            
            $this->checkTime();            
        }
        
    }
    
    
    
    public function prepare()
    {
        $this->removeAllData();
        $this->scanPosts();

        $this->checkPostGuid();
        $this->checkImageExists();
        $this->checkFilePosition();
        $this->checkWpAttachedFileIsDirty();
        $this->checkWpAttachedFile();
        $this->checkWpAttachmentMetadataIsDirty();
        $this->checkWpAttachmentMetadata();
        
        $states = array(
            'init',
            'guid',
            'file_exists',
            'file_position',
            'wp_attached_file_is_dirty',
            'wp_attached_file',
            'wp_attachment_metadata_is_dirty',
            'wp_attachment_metadata',
        );
        foreach($states as $status) {
            $this->writeln($status.': '.count($this->getImagesByStatus($status)));
        }
    }
    
    
    
    
    public function repare()
    {
        $this->fixGuidNotOk();
        $this->fixWpAttachedFileIsDirty();
        $this->fixWpAttachmentMetadataIsDirty();
        $this->fixPositionNotOkFoundPath();
        $this->fixWpAttachedFileNotOk();
        $this->fixWpAttachmentMetadataNotOk();
        $this->fixPositionNotOkFoundPathMore();
    }
    

}
