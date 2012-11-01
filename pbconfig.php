<?php
namespace popoblog;

use susam\SCTK;

class PBConfig
{
    public $postSuffix;

    public function __construct()
    {
        $this->postSuffix = '.p.php';
        $this->commentsSuffix = '.c.php';
        $this->oldIndexSuffix = '_OLD_';
        $this->newIndexSuffix = '_NEW_';
        $this->postsIndex = 'post.php';

        $this->postParam = 'p';
        $this->listParam = 'page';
        $this->tagsParam = 'tags';
        $this->indexParam = 'index';
        $this->indexCreateValue = 'create';
        $this->indexDeleteValue = 'delete';

        $this->commentParam = 'c';

        $this->oldestPostID = 'oldest';
        $this->latestPostID = 'latest';
        $this->randomPostID = 'random';
        $this->rssID = 'rss';
        $this->commentID = 'comment';
        $this->commentsHTMLElementID = 'comments';

        $this->postsPerList = 5;
        $this->postsPerFeed = 20;

        $this->submitFormParam = 'submit';
        $this->commenterNameParam = 'name';
        $this->commenterURLParam = 'url';
        $this->commenterEmailParam = 'email';
        $this->commenterTextParam = 'text';
    }

    /**
     *
     * @var string
     */
    public $path;
    public $dataDir;
    public $indexDir;

    // Labels.
    public $feedTitle;
    public $feedDescription;

    // Logistics.
    public $mailerEmail;
    public $adminEmail;
    public $timezone;

    public function getIndexDir()
    {
        return $this->indexDir;
    }

    public function getOldIndexDir()
    {
        return substr($this->indexDir, 0, -1) . $this->oldIndexSuffix .  '/';
    }

    public function getNewIndexDir()
    {
        return substr($this->indexDir, 0, -1) . $this->newIndexSuffix .  '/';
    }

    public function getPostsIndex($new = FALSE)
    {
        if ($new)
            return $this->getNewIndexDir() . $this->postsIndex;
        else
            return $this->getIndexDir() . $this->postsIndex;
    }

    public function getPostPath($postID)
    {
        return $this->dataDir .  $postID . $this->postSuffix;
    }

    public function getCommentsPath($postID)
    {
        return $this->dataDir .  $postID . $this->commentsSuffix;
    }

    public function getBlogURL()
    {
        return SCTK::getHostURL() . $this->path;
    }

    public function getLatestPostURL()
    {
        return $this->getBlogURL() . $this->latestPostID . "/";
    }

    public function getRandomPostURL()
    {
        return $this->getBlogURL() . $this->randomPostID . "/";
    }

    public function getIndexURL()
    {
        return $this->getBlogURL() . '?' . $this->indexParam;
    }

    public function getRSSURL()
    {
        return $this->getBlogURL() . $this->rssID . "/";
    }

    public function getPostURL($postID)
    {
        $PostsURL = $this->getBlogURL();
        return "{$PostsURL}{$postID}/";
    }

    public function getCommentsURL($postID)
    {
        $postURL = $this->getPostURL($postID);
        return $postURL . '#' . $this->commentsHTMLElementID;
    }

    public function getCommentFormURL($postID = '')
    {
        $blogURL = $this->getBlogURL();

        // Add query only post ID is present. Otherwise, do not add an
        // query part and output a plain URL. post ID won't be present
        // when PBView is calling this function to output canonical URL.
        $query = $postID != '' ? '?' . $this->commentParam . '=' . $postID : '';
        return $blogURL . $this->commentID . '/' . $query;
    }

    public function getAdjacentListURLs($listID, $maxListID, $tags)
    {
        $minListID = 1;

        $nextListURL = NULL;
        $prevListURL = NULL;

        if ($listID < $maxListID) {
            $nextListURL = $this->getListURL($listID + 1, $tags);
        }
        
        if ($listID > $minListID) {
            $prevListURL = $this->getListURL($listID - 1, $tags);
        }

        return array($prevListURL, $nextListURL);
    }

    public function getCommentID($postID, $commentLocalID)
    {
        return "{$postID}-{$commentLocalID}";
    }

    public function getCommentURL($postID, $commentLocalID)
    {
        $postURL = $this->getPostURL($postID);
        $commentID = $this->getCommentID($postID, $commentLocalID);
        return "$postURL#$commentID";
    }

    public function getListURL($listID, $tags)
    {
        $blogURL = $this->getBlogURL();

        $delim = '?';
        $listParam = '';
        $tagsParam = '';

        if (count($tags) > 0) {
            $tagsParam = $delim . $this->tagsParam . '=' . implode('+', $tags);
            $delim = '&';
        }

        if ($listID > 1) {
            $listParam = $delim . $this->listParam . "=$listID";
            $delim = '&';
        }

        return "{$blogURL}{$tagsParam}{$listParam}";
    }
}
?>
