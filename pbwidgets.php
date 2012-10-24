<?php
class PBWidgets
{
    /**
     * A flag that is TRUE if an error has occurred.
     */
    private $error;


    /**
     * Configuration object.
     *
     * @var PBConfig
     */
    private $config;


    /**
     * View to be used to display web user interface.
     *
     * @var PBView
     */
    private $view;


    /**
     * Database access object.
     *
     * @var PBDB
     */
    private $db;


    /**
     * Index access object.
     *
     * @var PBIndex
     */
    private $index;


    /**
     * Constructs an instance of PBWidgets.
     *
     * A PBConfig object must to be passed to the constructor. All
     * public properties of this object must be set. A view object may
     * be passed to override the default POPOBlog view. The view object
     * should be a subclass of PBView. If a view object is not passed to
     * the constructor, a PBView object is used by default.
     *
     * @param PBConfig $config Configuration object.
     * @param PBView   $view   View object.
     *
     * @throws PBDBException When index reading fails.
     */
    public function __construct($config, $view = NULL)
    {
        // We'll assume no errors have happened in the beginning.
        $this->error = FALSE;

        // Save the configuration for use by other methods.
        $this->config = $config;

        // Configure template.
        $this->view = isset($view) ? $view : new PBView(); 
        $this->view->setConfig($config);

        // Configure database access.
        $this->db = new PBDB($config);

        // Read index. This statement can cause PBDBException.
        try {
            $this->index = $this->db->readIndex();
        } catch (PBDBException $e) {
            $this->error = TRUE;
            $this->view->systemErrors = array('Could not read index.');
            return;
        }
        $this->view->setIndex($this->index);
    }

    public function post($postID)
    {
        if ($this->error) {
            $this->view->systemErrorsInWidget();
            return;
        }

        $post = $this->db->readPost($postID);
        $comments = $this->db->readComments($postID);
        $this->view->setPost($postID, $post, $comments);
        $this->view->post();
    }

    public function latestPost()
    {
        $this->post($this->index->latestPostID);
    }

    public function previousPost()
    {
        $this->post($this->index->latestPostID - 1);
    }

    public function randomPost()
    {
        $this->post(array_rand(array_slice($this->index->posts, 0, -1, TRUE)));
    }

    public function postsList($n = 10)
    {
        if ($this->error) {
            $this->view->systemErrorsInWidget();
            return;
        }

        $list = "<ul>\n";

        $postIDs = $this->index->posts;
        $postIDs = array_reverse(array_slice($postIDs, 0 - $n, NULL, TRUE), TRUE);
        foreach ($postIDs as $postID => $postTime) {
            $url = $this->config->getPostURL($postID);
            $title = $this->db->readPost($postID)->title;
            $list .= "<li><a href=\"$url\">$title</a></li>\n";
        }

        $list .= "</ul>\n";
        echo $list;
    }

    public function tagsList()
    {
        if ($this->error) {
            $this->view->systemErrorsInWidget();
            return;
        }

        $list = "<ul>\n";

        foreach ($this->index->tagCounts as $tag => $count) {
            if ($tag == PBIndex::ALL_TAGS) {
                $postsLabel = SCTK::plural($this->view->getPostLabel());
                $tagDisplay = "all";
                $tagURL = $this->config->getListURL(1, array ());
            } else {
                $tagDisplay = $tag;
                $tagURL = $this->config->getListURL(1, array ($tag));
            }
            $list .= "<li><a href=\"$tagURL\">$tagDisplay</a> ($count)</li>\n";
        }

        $list .= "</ul>\n";
        echo $list;
    }
}
?>
