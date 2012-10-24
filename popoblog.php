<?php
/**
 * POPOBlog
 *
 * This script provides the POPOBlog class that handles requests and
 * displays web user interface.
 *
 * LICENSE:
 *
 * Copyright (c) 2012 Susam Pal
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *     1. Redistributions of source code must retain the above copyright
 *        notice, this list of conditions and the following disclaimer.
 *     2. Redistributions in binary form must reproduce the above
 *        copyright notice, this list of conditions and the following
 *        disclaimer in the documentation and/or other materials provided
 *        with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://susam.in/licenses/bsd/ Simplified BSD License
 * @version 0.1
 */


/**
 * Common toolkit.
 */
require 'sctk.php';

/**
 * Includes POPOBlog configuration class.
 */
require 'pbconfig.php';

/**
 * Includes POPOBlog database access class.
 */
require 'pbdb.php';

/**
 * Includes POPOBlog index access classes.
 */
require 'pbindex.php';

/**
 * Includes POPOBlog view.
 */
require 'pbview.php';

/**
 * Includes comment form class.
 */
require 'pbcommentform.php';

/**
 * Includes POPOBlog widgets.
 */
require 'pbwidgets.php';


/**
 * POPOBlog.
 *
 * This class handles requests and displays web user interface.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */
class POPOBlog
{
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
     * Represents normal response to a request for a list.
     *
     * This is one of the possible responses to a request for a list.
     * This response indicates that the list has been found and it
     * should be displayed to the user.
     */
    const LIST_OK = 0;


    /**
     * Represents a response that list has not been found.
     *
     * This is one of the possible responses to a request for a list.
     * This response indicates that the list has not been found and an
     * error message should be displayed to the user.
     */
    const LIST_NOT_FOUND = 1;


    /**
     * Constructs an instance of POPOBlog.
     *
     * A PBConfig object must to be passed to the constructor. All
     * public properties of this object must be set. A view object may
     * be passed to override the default POPOBlog view. The view object
     * should be a subclass of PBView. If a view object is not passed to
     * the constructor, a PBView object is used by default.
     *
     * @param PBConfig $config Configuration object.
     * @param PBView   $view   View object.
     */
    public function __construct($config, $view = NULL)
    {
        // Save the configuration for use by other methods.
        $this->config = $config;

        // Configure template.
        $this->view = isset($view) ? $view : new PBView(); 
        $this->view->setConfig($config);

        // Configure database access.
        $this->db = new PBDB($config);

        // Index is initialized later. It can't be initialized here
        // because we display a system error if the index doesn't exist,
        // and an index may not exist when an index management request
        // occurs, so we initialize this later when we have determined
        // that the request is not for index management.
        $this->index = NULL;
    }


    /**
     * Processes request and generates output to display to the user on
     * web user interface.
     *
     * This method determines whether an HTTP GET or an HTTP POST
     * request was used to invoke the script that uses this class. HTTP
     * GET request occurs when a post or a list of posts is requested.
     * HTTP POST request occurs due to submission of a comment. After
     * determining the type of request, this method invokes the
     * appropriate methods to handle the request.
     */
    public function run()
    {
        if (isset($_POST[$this->config->commentParam]))
            $this->processPostRequest();
        else
            $this->processGetRequest();
    }


    /**
     * Processes the HTTP POST request that was used to invoke this
     * script that uses this class. A post request is used to submit
     * comments to a blog post.
     */
    private function processPostRequest()
    {
        // Before doing anything other than index management,
        // we need to get the index. The index is required by
        // PBCommentForm to decide whether the post ID submitted with
        // the request is valid.
        try {
            $this->index = $this->db->readIndex();
        } catch (PBDBException $e) {
            $this->view->pageTitle = 'System error';
            $this->view->systemErrors = array('Index error: ' .
                                              $e->getMessage());
            $this->emailErrorsToAdmin('ERROR: Index error during POST');
            SCTK::header(500);
            $this->displaySystemErrors();
            return;
        }

        // Index is present, so we can handle the comment submission.
        $commentFormData = new PBCommentForm($this->config, $this->index);
        $commentFormData->readDataFromRequest();
        $commentFormData->validate();

        // Send email to admin.
        $this->view->commentFormData = $commentFormData;
        $this->emailCommentToAdmin($commentFormData);

        // Send HTML response to the user.
        $commentFormData->setDataToCookie();
        $this->view->commentForm();
    }


    /**
     * Processes the HTTP GET request that was used to invoke the script
     * that uses this class.
     *
     * This method first tries to read the index. If it fails, it
     * displays a system error and alerts the admin. If the index is
     * read successfully, it determines the type of request and
     * processes it accordingly.
     */
    private function processGetRequest()
    {

        $postID = SCTK::get($_GET, $this->config->postParam);

        if ($postID == '') {
            $urlTokens = explode('?', $_SERVER['REQUEST_URI'], 2);
            $requestURI = $urlTokens[0];
            if ($requestURI[strlen($requestURI) - 1] != '/') {
                SCTK::redirect($this->config->getBlogURL(), 301);
                return;
            }
        } else if ($postID[strlen($postID) - 1] != '/') {
            SCTK::redirect($this->config->getPostURL($postID), 301);
            return;
        } else {
            $postID = substr($postID, 0, -1);
        }

        // Process index management request.
        if ($postID == '' && isset($_GET[$this->config->indexParam])) {
            $this->processIndexRequest();
            return;
        }

        // Before doing anything other than index management,
        // we need to get the index.
        try {
            $this->index = $this->db->readIndex();
        } catch (PBDBException $e) {
            // Configure view for system error.
            $this->view->pageTitle = 'System error';
            $this->view->systemErrors = array('Index error: ' .
                                              $e->getMessage());

            // Send the errors to admin before displaying it to user.
            $this->emailErrorsToAdmin('ERROR: Index error during GET');
            SCTK::header(500);
            $this->displaySystemErrors();
            return;
        }

        // We have found the index. The view needs to have the index at
        // all times. Set the index in the view.
        //
        $this->view->setIndex($this->index);

        // Comment form request.
        if ($postID == $this->config->commentID) {
            $postID = SCTK::get($_GET, $this->config->commentParam, '');
            $this->view->postID = $postID;
            $this->view->commentFormData = new PBCommentForm($this->config,
                                                             $this->index);
            $this->view->commentFormData->readDataFromCookie();
            $this->view->commentForm();
            return;
        }

        // RSS.
        if ($postID == $this->config->rssID) {
            $this->displayRSS();
            return;
        }

        // Oldest post.
        if ($postID == $this->config->oldestPostID) {
            $oldestPostID = $this->index->oldestPostID;
            $oldestPostURL = $this->config->getPostURL($oldestPostID);
            SCTK::redirect($oldestPostURL, 307); 
            return;
        }

        // Latest post.
        if ($postID == $this->config->latestPostID) {
            $latestPostID = $this->index->latestPostID;
            $latestPostURL = $this->config->getPostURL($latestPostID);
            SCTK::redirect($latestPostURL, 307); 
            return;
        }

        // Random post.
        if ($postID == $this->config->randomPostID) {
            $randomPostID = array_rand($this->index->posts);
            $randomPostURL = $this->config->getPostURL($randomPostID);
            SCTK::redirect($randomPostURL, 307); 
            return;
        }

        // List of posts.
        if ($postID == '') {
            $this->processListRequest();
            return;
        }

        // Invalid post name leads to HTTP 404.
        if (! isset($this->index->posts[$postID])) {
            $this->emailErrorsToAdmin('WARN: Post not found');
            SCTK::header(404); 
            $this->view->pageTitle = 'Not found';
            $this->view->beginPage();
            $this->view->postNotFound();
            $this->view->endPage();
            return;
        }

        // Display post.
        $this->displayPost($postID);
    }


    /**
     * Processes index management request.
     *
     * An index command is read from the 'index' parameter of the query
     * string. The possible values are:
     *
     *   create - Create the index.
     *   delete - Delete the index.
     *
     * If no value is specified, e.g. ?index, the create command is
     * assumed by default. If any other value is specified, a system
     * error is displayed and the administrator is alerted by email.
     */
    private function processIndexRequest()
    {
        $indexCommand = $_GET[$this->config->indexParam];
        if ($indexCommand == '') {
            $indexCommand = $this->config->indexCreateValue;
        }

        $indexKit = new PBIndexKit($this->config);

        $this->view->pageTitle = 'Index management';
        switch ($indexCommand) {
            case '':
            case $this->config->indexCreateValue:
                $this->view->beginPage();
                $indexKit->create();
                $this->view->endPage();
                break;

            case $this->config->indexDeleteValue:
                $this->view->beginPage();
                $indexKit->delete();
                $this->view->endPage();
                break;

            default:
                $this->view->requestErrors = array('Invalid index command.');
                $this->emailErrorsToAdmin('WARN: Invalid index command');
                SCTK::header(400);
                $this->displayRequestErrors('Invalid index command.');
                break;
        }
    }


    /**
     * Displays requested list of posts.
     *
     * This method reads the list ID requested from the 'page' parameter
     * in the request. If the value can be converted to an integer, it
     * serves the list with the specified list ID. If it cannot be
     * converted to an integer or if the list ID is out of range, a
     * request error is displayed.
     *
     * The posts to be displayed in the list are filtered by tags if the
     * 'tags' parameter is present. One or more tags may be specified as
     * value of this parameter. Multiple tags must be separated by a
     * space.
     */
    private function processListRequest()
    {
        $listID = isset($_GET[$this->config->listParam])
                  ? intval($_GET[$this->config->listParam]) : 1;
        $tags = isset($_GET[$this->config->tagsParam])
                ? explode(' ', $_GET[$this->config->tagsParam]) : array();

        list($response, $maxListID, $listPostIDs) =
                $this->getListPostIDs($listID, $tags);

        // Request error if list is not found.
        if ($response == self::LIST_NOT_FOUND) {
            $oldestListURL = $this->config->getListURL($maxListID,
                                                       $tags);

            $this->emailErrorsToAdmin('WARN: List not found');
            SCTK::header(404); 
            $this->view->oldestListURL = $oldestListURL;
            $this->view->pageTitle = 'Not found';
            $this->view->beginPage();
            $this->view->listNotFound();
            $this->view->endPage();
            return;
        }

        // Display list.
        $this->view->listID = $listID;
        $this->view->maxListID = $maxListID;
        $this->view->tags = $tags;

        $this->view->beginPage();
        $this->displayList($listPostIDs);
        $this->view->afterList();
        $this->view->endPage();
        return;
    }


    /**
     * Returns the post IDs for the specified list ID and post tags.
     * This method computes the post IDs of the posts with the specified
     * post tags to be displayed in the list with the specified list ID.
     *
     * @param integer $listID List ID.
     * @param array   $tags   Post tags.
     *
     * @return array An array consisting of three values: Response to
     * list request, maximum list ID and post IDs of posts to be displayed.
     */
    private function getListPostIDs($listID, $tags)
    {
        // Select post IDs for requested tags.
        if (count($tags) == 0) {
            $postIDs = array_keys($this->index->posts);
        } else {
            $postIDs = array();
            foreach ($this->index->tags as $postID => $postTags) {
                foreach ($tags as $requestedTag) {
                    if (in_array($requestedTag, $postTags)) {
                        $postIDs[] = $postID;    
                        break; // Move on to the next post.
                    }
                }
            }
        }

        $totalPosts = count($postIDs);
        $totalLists = ceil($totalPosts / $this->config->postsPerList);

        // Return error if list requested is out of range.
        if ($listID < 1 || $listID > $totalLists) {
            return array(self::LIST_NOT_FOUND, $totalLists, array());
        }

        $maxIndex = ($totalPosts - 1) -
                    ($listID - 1) * $this->config->postsPerList;
        $minIndex = max($maxIndex - $this->config->postsPerList + 1, 0);
        $length = $maxIndex - $minIndex + 1;

        $listPostIDs = array_reverse(array_slice($postIDs,
                                                 $minIndex,
                                                 $length));
        return array(self::LIST_OK, $totalLists, $listPostIDs);
    }



    /**
     * Displays the specified list of posts.
     *
     * @param array $listPostIDs List of post IDs.
     */
    private function displayList($listPostIDs)
    {
        foreach ($listPostIDs as $postID) {

            $post = $this->db->readPost($postID);
            $comments = $this->db->readComments($postID);
            $this->view->setPost($postID, $post, $comments);

            echo "<div class=\"post-item\">";
            $this->view->fullPostView = FALSE;
            $this->view->beforePost();
            $this->view->post();
            $this->view->afterPost();
            echo "</div>";

        }
    }


    /**
     * Displays the specified post in its own page.
     *
     * @param string $postID Post ID of the post to be displayed.
     */
    private function displayPost($postID)
    {

        $post = $this->db->readPost($postID);
        $comments = $this->db->readComments($postID);
        $this->view->setPost($postID, $post, $comments);

        $this->view->beginPage();
        $this->view->fullPostView = TRUE;
        $this->view->beforePost();
        $this->view->post();
        $this->view->afterPost();
        $this->view->comments();
        $this->view->beforeCommentFormFrame();
        $this->view->commentFormFrame();
        $this->view->afterCommentFormFrame();
        $this->view->endPage();
    }


    /**
     * Sends RSS of the blog as response.
     */
    private function displayRSS()
    {
        $postIDs = array_keys($this->index->posts);
        $latestPostIndex = count($postIDs) - 1;
        $rssFirstPostIndex = max($latestPostIndex -
                                 $this->config->postsPerFeed + 1, 0);
        $length = $latestPostIndex - $rssFirstPostIndex + 1;

        $rssPostIDs = array_reverse(array_slice($postIDs,
                                                $rssFirstPostIndex,
                                                $length));
        header('Content-Type: application/xml');
        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>

    <title><?php echo $this->config->feedTitle ?></title>
    <link><?php echo $this->config->getBlogURL() ?></link>
    <description><?php echo $this->config->feedDescription ?></description>
    <language>en</language>
<?php
    foreach ($rssPostIDs as $postID) {
        $post = $this->db->readPost($postID);
        $comments = $this->db->readComments($postID);
        $this->view->setPost($postID, $post, $comments);
        $this->view->rssItem();
    }
?>
</channel>
</rss>
<?php
    }


    /**
     * Displays request errors.
     */
    private function displayRequestErrors()
    {
        $this->view->beginPage();
        $this->view->requestErrors();
        $this->view->endPage();
    }


    /**
     * Displays system errors.
     */
    private function displaySystemErrors()
    {
        $this->view->beginPage();
        $this->view->systemErrors();
        $this->view->endPage();
    }


    /**
     * Emails errors to admin.
     *
     * @param string $subject Subject of the email to be sent.
     */
    private function emailErrorsToAdmin($subject)
    {
        $subject .= ': ' . SCTK::getURL();
        $this->emailAdmin($subject,
                          $this->view->getErrorsEmailBody());
    }


    /**
     * Emails comment notification to admin.
     *
     * @param PBCommentForm $commentFormData Comment form data.
     */
    private function emailCommentToAdmin($commentFormData)
    {
        if ($commentFormData->name != '') {
            $replyTo = "Reply-To: $commentFormData->name " .
                       "<$commentFormData->email>\r\n";
        } else {
            $replyTo = '';
        }

        $postID = $commentFormData->postID;
        if (isset($this->index->posts[$postID])) {
            $post = $this->db->readPost($postID);
            $this->view->setPost($postID, $post);
        }

        $this->emailAdmin($this->view->getCommentEmailSubject(),
                          $this->view->getCommentEmailBody(),
                          $replyTo);
    }


    /**
     * Send email to admin.
     *
     * @param string subject       Subject of the email.
     * @param string body          Body of the email.
     * @param strign replyToHeader Optional 'Reply-To' header.
     */
    private function emailAdmin($subject, $body, $replyToHeader = '')
    {
        $headers = "From: {$this->config->mailerName} " .
                   "<{$this->config->mailerEmail}>\r\n" .

                   // "To: {$this->config->adminName} " .
                   // "<{$this->config->adminEmail}>\r\n" .

                   $replyToHeader .

                   'X-Mailer: PHP/' . phpversion();

        return mail($this->config->adminEmail, $subject, $body, $headers);
    }
}


/**
 * POPOBlog post.
 *
 * This class represents a post.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */
class PBPost
{
    /**
     * A flag that is TRUE if this post is disabled; FALSE otherwise.
     *
     * @var boolean
     */
    public $disabled;


    /**
     * A flag that is TRUE if comments on the post is disabled;
     * FALSE otherwise.
     *
     * @var boolean
     */
    public $locked;


    /**
     * A flag that is TRUE if comments on the post is moderated;
     * FALSE otherwise.
     *
     * @var boolean 
     */
    public $moderated;


    /**
     * Date when the post was written. The date should be an English
     * textual datetime description that can be parsed by PHP's
     * strtotime() function.
     *
     * @var string
     */
    public $date;


    /**
     * Title of the post.
     *
     * @var string
     */
    public $title;


    /**
     * Tags associated with the post. This is an array of strings.
     *
     * @var array
     */
    public $tags;


    /**
     * Text of the blog post.
     */
    public $text;


    /**
     * Constructs an instance of a blog post and initializes its
     * properties to default values. Boolean properties are initialized
     * to FALSE. Strings are initialized to zero-length strings. Arrays
     * are initialized to zero-length arrays.
     */
    public function __construct()
    {
        $this->disabled = FALSE;
        $this->locked = FALSE;
        $this->moderated = FALSE;
        $this->date = '';
        $this->title = '';
        $this->tags = array();
        $this->text = '';
    }
}


/**
 * POPOBlog comment.
 *
 * This class represents a comment.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */
class PBComment
{
    /**
     * A flag that is TRUE if this comment is disabled; FALSE otherwise.
     *
     * @var boolean
     */
    public $disabled;


    /**
     * Date when the comment was written. The date should be an English
     * textual datetime description that can be parsed by PHP's
     * strtotime() function.
     *
     * @var string
     */
    public $date;


    /**
     * Name of the person that wrote the comment.
     *
     * @var string
     */
    public $name;


    /**
     * Email address of the person that wrote the comment.
     *
     * @var string
     */
    public $email;


    /**
     * URL of the person that wrote the comment.
     *
     * @var string
     */
    public $url;


    /**
     * A flag that is true if the person who wrote the comment is the
     * author of the blog or is associated with the blog.
     *
     * @var boolean
     */
    public $authority;


    /**
     * Text of the comment.
     */
    public $text;


    /**
     * Constructs an instance of a blog post and initializes its
     * properties to default values. Boolean properties are initialized
     * to FALSE. Strings are initialized to zero-length strings.
     */
    public function __construct()
    {
        $this->disabled = FALSE;
        $this->date = '';
        $this->name = '';
        $this->email = '';
        $this->url = '';
        $this->authority = FALSE;
        $this->text = '';
    }
}
?>
