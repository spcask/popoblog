<?php
namespace popoblog;

use Exception;
use susam\SCTK;

class PBDB
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function readIndex()
    {
        $indexPath = $this->config->getPostsIndex();

        if (! file_exists($indexPath))
            throw new PBDBException('Index is missing.');

        $included = include $this->config->getPostsIndex();

        if (! $included)
            throw new PBDBException('Could not read index.');

        $index->init($this->config);
        return $index;
    }

    public function writeIndex($index)
    {
        $oldIndexDir = $this->config->getOldIndexDir();
        $newIndexDir = $this->config->getNewIndexDir();

        // Clean up any leftover temporary indexes.
        SCTK::rm($oldIndexDir);
        if (file_exists($oldIndexDir))
            throw new PBDBException('Could not delete old left-over index.');

        SCTK::rm($newIndexDir);
        if (file_exists($newIndexDir))
            throw new PBDBException('Could not delete new left-over index.');


        // Write new index in a temporary index.
        $success = mkdir($newIndexDir);
        if (! $success) {
            throw new PBDBException('Could not create new index directory.');
        }

        $f = fopen($this->config->getPostsIndex(TRUE), 'w');
        if ($f == FALSE) {
            throw new PBDBException('Could not open new index for writing.');
        }

        fwrite($f, $this->indexToPOPO($index));
        fclose($f);

        // Replace live index with the new index.
        $indexDir = $this->config->getIndexDir();
        if (file_exists($indexDir))
            rename($indexDir, $oldIndexDir);
        rename($newIndexDir, $indexDir);
        SCTK::rm($oldIndexDir);
    }

    public function deleteIndex()
    {
        SCTK::rm($this->config->getIndexDir());
        SCTK::rm($this->config->getOldIndexDir());
        SCTK::rm($this->config->getNewIndexDir());
    }

    public function readAllPosts()
    {
        $postData = array();
        if (! file_exists($this->config->dataDir)) {
            throw new PBDBException('Data directory does not exist.');
        }

        foreach (glob($this->config->dataDir . '*' .
                      $this->config->postSuffix)
                 as $postPHP) {

            require $postPHP;

            // If the post is disabled, skip it.
            if ($post->disabled) {
                continue;
            }

            // If the post has a future date, skip it.
            date_default_timezone_set($this->config->timezone);
            $publicationTime = strtotime($post->date);
            if ($publicationTime > time()) {
                echo "$publicationTime<br>";
                echo time() . "<br>";
                continue;
            }

            $postID = $this->getPostID($postPHP);
            $postData[$postID] = $post;
        }

        if (count($postData) == 0) {
            throw new PBDBException('No posts found in data directory.');
        }

        return $postData;
    }

    public function readPost($postID)
    {
        require $this->config->getPostPath($postID);
        return $post;
    }

    public function readComments($postID)
    {
        require $this->config->getCommentsPath($postID);
        return $comments;
    }

    private function getPostID($filepath)
    {
        return substr(basename($filepath), 0,
                      -strlen($this->config->postSuffix));
    }

    private function indexToPOPO($index)
    {
        return "<?php\n" .

               '$index = new popoblog\PBIndex();' . "\n" .

               '$index->oldestPostID = ' . 
               var_export($index->oldestPostID, TRUE) .
               ";\n" .

               '$index->latestPostID = ' . 
               var_export($index->latestPostID, TRUE) .
               ";\n" .

               '$index->posts = ' . 
               var_export($index->posts, TRUE) .
               ";\n" .

               '$index->tags = ' . 
               var_export($index->tags, TRUE) .
               ";\n" .
               
               '$index->tagCounts = ' . 
               var_export($index->tagCounts, TRUE) .
               ";\n" .

               "?>\n";
    }
}


/**
 * POPOBlog data access exception.
 *
 * This class represents an error due to data access.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */
class PBDBException extends Exception
{
}
?>
