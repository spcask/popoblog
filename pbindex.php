<?php
/**
 * POPOBlog index.
 *
 * This script provides the classes to access and manage POPOBlog index.
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */

namespace popoblog;

/**
 * POPOBlog index management kit.
 *
 * This class provides the methods to create or delete index.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */
class PBIndexKit
{
    private $config;
    private $db;

    public function __construct($config)
    {
        $this->config = $config;
        $this->db = new PBDB($config);
    }

    public function create()
    {
        echo '<div class="popoblog">' . "\n";
        echo "<h2>Index management</h2>\n";
        echo "<p>Index creation progress:</p>\n";
        echo "<ol>\n";
        flush();

        try {
            echo "<li>Computing index ...</li>\n";
            $index = $this->compute();
            flush();

            echo "<li>Writing index ...</li>\n";
            $this->db->writeIndex($index);
            flush();
        } catch (PBDBException $e) {
            echo "</ol>\n";
            echo "<p><b>ERROR:</b> {$e->getMessage()}</p>\n";
            echo "</div>";
            flush();
            return;
        }

        echo "<li>Index is LIVE!</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        flush();
    }

    public function delete()
    {
        echo "<h2>Index management</h2>\n";
        echo "<p>Index deletion progress:</p>\n";
        echo "<ol>\n";
        echo '<div class="popoblog">' . "\n";
        echo "<ol>\n";
        echo "<li>Deleting index ...</li>\n";
        flush();

        $this->db->deleteIndex();

        echo "<li>Index has been deleted.</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        flush();
    }

    /**
     *
     * @throws PBDBException When data directory doesn't exist or when
     *                       no posts are found in the data directory.
     */
    private function compute()
    {
        // Get all posts and arrange them in chronological order.
        $postData = $this->db->readAllPosts();
        uasort($postData, function($a, $b) {
            return strtotime($a->date) - strtotime($b->date);
        });

        $index = new PBIndex();
        $index->tagCounts[PBIndex::ALL_TAGS] = 0;
        foreach ($postData as $postID => $item) {
            $index->posts[$postID] = strtotime($item->date);
            $index->tags[$postID] = $item->tags;
            foreach ($item->tags as $tag) {
                if (isset($index->tagCounts[$tag])) {
                    $index->tagCounts[$tag]++;
                } else {
                    $index->tagCounts[$tag] = 1;
                }
                $index->tagCounts[PBIndex::ALL_TAGS]++;
            }
        }

        // Sort the tags by count.
        arsort($index->tagCounts);

        // Get the oldest and latest post.
        $postIDs = array_keys($index->posts);
        $index->oldestPostID = $postIDs[0];
        $index->latestPostID = $postIDs[count($postIDs) - 1];
        
        return $index;
    }
}

class PBIndex
{
    public $posts;
    public $tags;
    public $tagCounts;
    private $config;

    const ALL_TAGS = '__all__';

    public function __construct()
    {
        $this->posts = array();
        $this->tags = array();
        $this->tagCounts = array();
        $this->config = NULL;
    }

    /**
     * Initializes the index with the specified configuration.
     *
     * This method helps the caller to set the configuration object in
     * the index after an index is loaded from an index PHP file.
     *
     * @param PBConfig $config Configuration object.
     */
    public function init($config)
    {
        $this->config = $config;
    }

    public function getAdjacentPostURLs($postID)
    {
        $postIDs = array_keys($this->posts);
        $pos = array_search($postID, $postIDs);

        $prevPostID = NULL;
        $nextPostID = NULL;

        if ($pos > 0) {
            $prevPostID = $this->config->getPostURL($postIDs[$pos - 1]);
        }

        if ($pos < count($postIDs) - 1) {
            $nextPostID = $this->config->getPostURL($postIDs[$pos + 1]);
        }

        return array($prevPostID, $nextPostID);
    }
}
?>
