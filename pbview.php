<?php
class PBView
{
    // Data.
    public $post;
    public $comments;
    public $comment;
    public $postID;
    public $listID;
    public $maxListID;
    public $tags;


    protected $enabledCommentsCount;

    // Post variables.
    protected $commentLocalID;
    protected $commentDisplayIndex;

    // Essential auxilliary information.
    public $config;
    public $index;

    // Page variables.
    public $fullPostView;

    /**
     * Data to be displayed as values of various input fields in the
     * comment form.
     *
     * @var PBComment
     */
    public $commentFormData;


    /**
     * HTML title of the page to be displayed in case of error.
     *
     * This title is displayed in the title bar of the browser.
     */
    public $pageTitle;

    /**
     * List of system errors.
     *
     * When there is a system error due to database queries, the list of
     * errors in this array is displayed to the user as well as sent as
     * an email notification to the admin.
     *
     * @var array
     */
    public $systemErrors;


    /**
     * List of request errors.
     *
     * When there is an error in the request URL, the list of errors in
     * this array is displayed to the user.
     *
     * @var array
     */
    public $requestErrors;

    public function __construct()
    {
        $this->postID = NULL;
        $this->pageTitle = '';
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setIndex($index)
    {
        $this->index = $index;
    }

    public function setPost($postID, $post, $comments = NULL)
    {
        $this->postID = $postID;
        $this->post = $post;
        $this->comments = $comments;
        $this->preprocessPost();
    }

    protected function preprocessPost()
    {
        // Set comments count.
        $this->enabledCommentsCount = 0;

        if (! isset($this->comments)) {
            return;
        }

        foreach ($this->comments as $comment) {
            if (! $comment->disabled) {
                $this->enabledCommentsCount++;
            }
        }
    }

    public function setComment($commentLocalID, $commentDisplayIndex, $comment)
    {
        $this->commentLocalID = $commentLocalID;
        $this->commentDisplayIndex = $commentDisplayIndex;
        $this->comment = $comment;
    }

    public function getPostLabel()
    {
        return 'post';
    }

    public function getListLabel()
    {
        return 'page';
    }

    public function getCommentLabel()
    {
        return 'comment';
    }

    protected function getLinkTags()
    {
        if (! isset($this->postID)) {
            return '';
        }

        $oldestPostID = $this->index->oldestPostID;
        $latestPostID = $this->index->latestPostID;

        $blogURL = $this->config->getBlogURL();
        $linkTags = "<link rel=\"up\" href=\"$blogURL\">\n";

        list($prevPostURL, $nextPostURL) =
                $this->index->getAdjacentPostURLs($this->postID);

        if ($this->postID != $oldestPostID) {
            $oldestPostURL = $this->config->getPostURL($oldestPostID);
            $linkTags .= "<link rel=\"first\" href=\"$oldestPostURL\">\n";
        }

        if (isset($prevPostURL)) {
            $linkTags .= "<link rel=\"prev\" href=\"$prevPostURL\">\n";
        }

        $postURL = $this->config->getPostURL($this->postID);
        $linkTags .= "<link rel=\"canonical\" href=\"$postURL\">\n";

        if (isset($nextPostURL)) {
            $linkTags .= "<link rel=\"next\" href=\"$nextPostURL\">\n";
        }

        if ($this->postID != $latestPostID) {
            $latestPostURL = $this->config->getPostURL($latestPostID);
            $linkTags .= "<link rel=\"last\" href=\"$latestPostURL\">\n";
        }

        return $linkTags;
    }

    protected function getRSSTag()
    {
        $rssURL = $this->config->getRSSURL();
        return '<link rel="alternate" type="application/rss+xml"' .
                    " title=\"RSS\" href=\"$rssURL\">\n";
    }

    protected function getPostURL()
    {
        return $this->config->getPostURL($this->postID);
    }

    protected function getPostDate()
    {
        date_default_timezone_set($this->config->timezone);
        return strftime('%A, %B %e, %Y', strtotime($this->post->date));
    }

    protected function postTags()
    {
        $taggedListURLs = array();
        foreach ($this->post->tags as $tag) {
            $taggedListURL = $this->config->getListURL(1, array($tag));
            $taggedListURLs[] = "[<a href=\"$taggedListURL\">$tag</a>]";
        }
        echo implode(' ', $taggedListURLs);
    }

    protected function getPostNavigationLinks()
    {
        $oldestPostID = $this->index->oldestPostID;
        $latestPostID = $this->index->latestPostID;

        $first = '<span title="First post">' .
                 '<span class="bar">|</span>&laquo;' .
                 '</span>';
        $prev = '<span title="Previous post">&laquo;</span>';
        $rand = '<span title="Random post">#</span>';
        $next = '<span title="Next post">&raquo;</span>';
        $last = '<span title="Latest post">' .
                '&raquo;<span class="bar">|</span>' .
                '</span>';

        // First.
        if ($this->postID != $oldestPostID) {
            $oldestPostURL = $this->config->getPostURL($oldestPostID);
            $first = "<a href=\"$oldestPostURL\">$first</a>";
        } else {
            $first = "<span class=\"inactive\">$first</span>";
        }

        // Previous and Next.
        list($prevPostURL, $nextPostURL) =
            $this->index->getAdjacentPostURLs($this->postID);

        // Previous.
        if (isset($prevPostURL)) {
            $prev = "<a href=\"$prevPostURL\">$prev</a>";
        } else {
            $prev = "<span class=\"inactive\">$prev</span>";
        }

        // Next.
        if (isset($nextPostURL)) {
            $next = "<a href=\"$nextPostURL\">$next</a>";
        } else {
            $next = "<span class=\"inactive\">$next</span>";
        }

        // Random.
        $randPostRedirectorURL = $this->config->getRandomPostURL();
        $rand = "<a href=\"$randPostRedirectorURL\">$rand</a>";

        // Last.
        if ($this->postID != $latestPostID) {
            $latestPostURL = $this->config->getPostURL($latestPostID);
            $last = "<a href=\"$latestPostURL\">$last</a>";
        } else {
            $last = "<span class=\"inactive\">$last</span>";
        }

        return <<<HTML
        $first
        $prev
        $rand
        $next
        $last
HTML;
    }

    protected function commentsCountLabel()
    {
        $n = $this->enabledCommentsCount;
        if ($n == 0) {
            echo "No {$this->getCommentLabel()}s";
        } else if ($n == 1) {
            echo "1 {$this->getCommentLabel()}";
        } else {
            echo "$n {$this->getCommentLabel()}s";
        }
    }

    protected function commenterName()
    {
        if ($this->comment->url) {
            echo "<a href=\"{$this->comment->url}\">" .
                   $this->comment->name . '</a>';
        } else {
            echo $this->comment->name;
        }
    }

    protected function commentVerb()
    {
        echo 'said';
    }

    protected function commentDate()
    {
        date_default_timezone_set($this->config->timezone);
        echo strftime('%A, %B %e, %Y %I:%M %p %Z',
                      strtotime($this->comment->date));
    }

    protected function getCommentClass()
    {
        if ($this->comment->authority) {
            return 'authority';
        } else {
            return 'regular';
        }
    }

    protected function getCommentFormURL()
    {
        return $this->config->getCommentFormURL($this->postID);
    }

    protected function getPageTitle()
    {
        if ($this->pageTitle != '') {
            return $this->pageTitle;
        } else if ($this->listID > 1) {
            return $this->config->blogTitle . 
                   " ({$this->getListLabel()} {$this->listID})";
        } else if ($this->listID == 1) {
            return $this->config->blogTitle;
        } else {
            return $this->post->title;
        }
    }

    public function beginPage()
    {
?>
<!DOCTYPE HTML>
<html>
<head>
<title><?php echo $this->getPageTitle() ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<?php echo $this->getLinkTags() ?>
<?php echo $this->getRSSTag() ?>
</head>
<body>
<h1>POPOBlog</h1>
<?php
    }

    public function endPage()
    {
?>
</body>
</html>
<?php
    }

    public function afterList()
    {
        list($newerListURL, $olderListURL)
                = $this->config->getAdjacentListURLs($this->listID,
                                                     $this->maxListID,
                                                     $this->tags);

        if (isset($newerListURL)) {
            $newer = "<a href=\"$newerListURL\">Newer</a>";
        } else {
            $newer = "<span class=\"inactive\">Newer</span>";
        }
        
        if (isset($olderListURL)) {
            $older = "<a href=\"$olderListURL\">Older</a>";
        } else {
            $older = "<span class=\"inactive\">Older</span>";
        }

        echo '<div class="list-nav">';
        echo "$newer | $older";
        echo '</div>';
    }

    public function beforePost()
    {
    }

    public function beginPost()
    {
?>
        <!-- POST ID: <?php echo $this->postID ?> -->
        <div class="post box">
            <div class="heading">
                <h2 class="title">
                    <a href="<?php echo
                    $this->getPostURL() ?>"><?php echo
                    $this->post->title ?></a>
                </h2>
                <?php
                if ($this->fullPostView) {
                ?>
                <!-- Navigation buttons on top -->
                <span class="post-nav">
                    <?php echo $this->getPostNavigationLinks() ?>
                </span>
                <?php
                }
                ?>
            </div> <!-- End post heading. -->

            <div class="content">
<?php
    }

    public function post()
    {
        $this->beginPost();
?>
        <div class="text">
            <?php echo $this->post->text ?>
        </div> <!-- End post text. -->
<?php
        $this->endPost();
?>
<?php
    }

    public function endPost()
    {
?>
        <!-- Tags -->
        <div class="widgets">
            <?php $this->postWidgets() ?>
        </div> <!-- End post widgets. -->
        </div> <!-- End post content. -->
        </div> <!-- End post. -->
        <!-- END DISPLAY POST -->
<?php
    }

    public function afterPost()
    {
    }

    public function postWidgets()
    {
?>
        <span class="tags">
            <?php $this->postTags() ?>
        </span>
<?php
        if (! $this->fullPostView) {
?>
            <!-- Comments count -->
            <span class="comments-count">
            <a href="<?php echo
                $this->config->getCommentsURL($this->postID) ?>"><?php echo
                $this->commentsCountLabel(); ?></a>
            </span>
<?
        }
    }

    public function comments()
    {
        $this->beforeComments();
        $this->beginComments();

        $this->commentsList();

        $this->endComments();
        $this->afterComments();
    }

    public function beforeComments()
    {
    }

    public function beginComments()
    {
        $commentsDivID = $this->config->commentsHTMLElementID;
?>
        <div id="<?php echo $commentsDivID ?>" class="box">
            <h3 class="heading">
            <?php echo $this->commentsCountLabel() ?>
            </h3>

<?php
    }

    public function commentsList()
    {
        echo "<div id=\"comments-list\">\n";
        $id = 0;
        $index = 0;
        foreach ($this->comments as $comment) {
            $id++;

            if ($comment->disabled)
                continue;

            $this->setComment($id, $index, $comment);
            $this->comment();
            $index++;
        }
        echo "</div> <!-- End comments list. -->\n";
    }

    public function endComments()
    {
?>
        </div> <!-- End comments. -->
<?php
    }

    public function afterComments()
    {
?>
        <div class="bottom-nav">
            <div class="post-nav">
                <?php echo $this->getPostNavigationLinks() ?>
            </div>
        </div>
<?php
    }

    public function comment()
    {
        $commentID = $this->config->getCommentID($this->postID,
                                                 $this->commentLocalID);
        $commentURL = $this->config->getCommentURL($this->postID,
                                                   $this->commentLocalID);
?>
        <!-- BEGIN DISPLAY COMMENT -->
        <div class="comment <?php echo $this->getCommentClass() ?>">
            <h4 class="heading"
                 name="<?php echo $commentID ?>"
                 id="<?php echo $commentID ?>">
                <?php $this->commenterName() ?>
                <?php $this->commentVerb() ?>:
            </h4>

            <div class="content">
                <div class="text">
                    <?php echo $this->comment->text ?>
                </div> <!-- End comment text. -->

                <div class="date">
                    <a href="<?php echo $commentURL ?>">
                    <?php $this->commentDate() ?>
                    </a>
                </div> <!-- End comment date. -->
            </div> <!-- End comment content. -->
        </div> <!-- End comment. -->
        <!-- END DISPLAY COMMENT -->
<?
    }

    public function getCommentFormFrameHeading()
    {
        return 'Post a ' . $this->getCommentLabel() . "\n";
    }

    public function beforeCommentFormFrame()
    {
    }

    public function commentFormNotice()
    {
    }

    public function commentFormFrame()
    {
?>
        <div id="comment-form-box" class="box">
            <h3 class="heading">
            <?php echo $this->getCommentFormFrameHeading() ?>
            </h3>
            <?php $this->commentFormNotice() ?>
            <iframe id="comment-form-frame" frameBorder="0"
                    src="<?php echo $this->getCommentFormURL() ?>">
            </iframe>
        </div>
<?
    }

    public function afterCommentFormFrame()
    {
    }

    public function rssItem()
    {
        $postID = $this->postID;
        $postURL = $this->getPostURL();
        $postTitle = htmlspecialchars($this->post->title, ENT_COMPAT, 'UTF-8');

        date_default_timezone_set($this->config->timezone);
        $pubDate =  strftime('%a, %d %b %Y %H:%M:%S %z',
                             strtotime($this->post->date));

        $postAuthor = $this->post->name;
?>
    <!-- <?php echo "#$postID - $postTitle" ?> -->
    <item>
        <title><?php echo $postTitle ?></title>
        <link><?php echo $postURL ?></link>
        <description>
            <?php $this->rssItemDescription() ?>
        </description>
        <pubDate><?php echo $pubDate ?></pubDate>
        <dc:creator><?php echo $postAuthor ?></dc:creator>
        <guid><?php echo $postURL ?></guid>
    </item>
<?php 
    }

    public function rssItemDescription()
    {
        echo htmlspecialchars($this->post->text, ENT_COMPAT, 'UTF-8');
    }

    public function listNotFound()
    {
?>
        <p>
        This <?php echo $this->getListLabel() ?> of <?php echo
        SCTK::plural($this->getPostLabel()) ?> does not exist.
        The oldest <?php echo $this->getListLabel() ?> of <?php echo
        SCTK::plural($this->getPostLabel()) ?> is available at
        <a href="<?php echo $this->oldestListURL ?>"><?php
            echo $this->oldestListURL ?></a>.
        </p>
<?php
    }


    public function postNotFound()
    {
        $latestPostID = $this->index->latestPostID;
        $latestPostURL = $this->config->getPostURL($latestPostID);
?>
        <p>
        This <?php echo $this->getPostLabel() ?> does not exist. The
        most recent post is <a href="<?php echo $latestPostURL ?>"><?php
        echo $latestPostURL ?></a>.

<?php
    }

    public function commentFormHead()
    {
?>
<title><?php echo 'Send ' . $this->getCommentLabel() ?></title>
<link rel="canonical" href="<?php echo $this->config->getCommentFormURL() ?>">
<style type="text/css">
/* Input style. */
#comment-form label {
    margin-top: 1em;
    display: block;
}

#comment-form input {
    width: 100%;
}

#comment-form textarea {
    width: 100%;
    height: 150px;
}

#comment-form input#submit {
    width: 62%;
    margin-left: auto;
    margin-right: auto;
    display: block;
    margin-top: 1em;
}

/* Status style. */
#comment-form div.status ul {
    margin: 5px;    
}

#comment-form div.errors {
    color: red;
    border: 3px double red;
}

#comment-form div.success {
    color: #008000;
    border: 3px double green;
}
</style>
<script type="text/javascript">
// Resize parent frame.
window.onload = function() {
    var f = parent.document.getElementById('comment-form-frame');
    var t = document.getElementById('text');
    function resizeParentFrame()
    {
        var frameHeight = document.body.offsetHeight + 30 + 'px';
        if (f.style.height != frameHeight)
            f.style.height = frameHeight;
    }
    t.onmouseup = resizeParentFrame;
    resizeParentFrame();
}
</script>
<?
    }

    public function commentForm()
    {
        $formURL = $this->config->getCommentFormURL($this->postID);
?>
<!DOCTYPE HTML>
<html>
<head>
<?php $this->commentFormHead() ?>
</head>
<body>
    <form id="comment-form" method="post" action="<?php echo $formURL ?>">

    <label for="name">Name:</label>
    <input type="text" id="name" name="name" required="required"
           value="<?php echo $this->commentFormData->name ?>">

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required="required"
           value="<?php echo $this->commentFormData->email ?>">

    <label for="url">URL:</label>
    <input type="url" id="url" name="url"
           value="<?php echo $this->commentFormData->url ?>">

    <label for="text">Comment:</label>
    <textarea id="text" name="text" required="required"><?php
        echo $this->commentFormData->text ?></textarea>

    <input type="hidden" id="<?php echo $this->config->commentParam ?>"
           name="<?php echo $this->config->commentParam ?>"
           value="<?php echo $this->postID ?>">

    <?php $this->commentFormStatus(); ?>

    <input type="submit" id="submit" name="submit" value="Send comment">
    </form>
</body>
</html>
<?
    }

    function commentFormStatus() {
        // If the form has not been processed, there is no status to
        // show.
        if (! $this->commentFormData->processed) {
            return;
        }

        if (count($this->commentFormData->errors) > 0) {
            echo "<div class=\"status errors\"><ul>\n";
            foreach ($this->commentFormData->errors as $error) {
                echo "<li>$error</li>\n";
            }
            echo "</ul></div>\n";
        } else {
?>
            <div class="status success">
            <ul>
            <li>
            <?php echo ucfirst($this->getCommentLabel()) ?> was
            submitted successfully. It will be published after
            moderation.
            </li>
            </ul>
            </div>
<?php
        }
    }

    public function requestErrors()
    {
        if (count($this->requestErrors) == 0)
            return;
?>
        <h2>Request error</h2>

        <div class="errors">
        <p>
        The following
        <?php echo SCTK::plural('error', count($this->requestErrors)) ?>
        occurred in the request.
        </p><ul>
<?php
        foreach ($this->requestErrors as $error) {
            echo "<li>$error</li>\n";
        }
?>
        </ul><p>
        This error should not occur if you clicked a link on this
        website. If you are sure that you have reached this page by
        clicking a link on this website, please come back and visit this
        URL again after a few hours. The administrator has been notified
        about this error already, and if there is an issue with this
        URL, it will be fixed as soon as possible.
        </p>
        </div> <!-- End errors. -->
<?php
    }


    /**
     * Displays system errors.
     *
     * System errors may occur due to issues in the system the script is
     * running on like missing database, permission issues, etc.
     */
    public function systemErrors()
    {
        if (count($this->systemErrors) == 0)
            return;
?>
        <h2>System error</h2>

        <div class="errors">
        <p>
        The following
        <?php echo SCTK::plural('error', count($this->systemErrors)) ?>
        occurred in the system.
        </p><ul>
<?php
        foreach ($this->systemErrors as $error) {
            echo "<li>$error</li>\n";
        }
?>
        </ul><p>
        This is not your fault. This indicates a problem in the system.
        The administrator has been notified about this error already.
        This issue will be fixed as soon as possible.
        </p>
        </div> <!-- End errors. -->
<?php
    }


    public function systemErrorsInWidget()
    {
        if (count($this->systemErrors) == 0)
            return;
?>
        <ul>
<?php
        foreach ($this->systemErrors as $error) {
            echo "<li>$error</li>\n";
        }
?>
        </ul>
<?
    }


    public function getErrorsEmailBody()
    {
        date_default_timezone_set($this->config->timezone);
        $date = var_export(strftime('%Y-%m-%d %H:%M:%S %Z',
                           time()), TRUE);
        $cookies = var_export($_COOKIE, TRUE);
        $requestErrors = var_export($this->requestErrors, TRUE);
        $systemErrors = var_export($this->systemErrors, TRUE);
        $referrer = isset($_SERVER['HTTP_REFERER']) ?  $_SERVER['HTTP_REFERER']
                                                    : '<none>';

        // Prepare email body.
        $body = <<<BODY
Request URI: http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}

Referrer: $referrer

Date: $date

Request errors: $requestErrors

System errors: $systemErrors

Remote address: {$_SERVER['REMOTE_ADDR']}

User agent: {$_SERVER['HTTP_USER_AGENT']}

Cookies: $cookies
BODY;
        return $body;
    }

    public function getCommentEmailSubject()
    {
        if (! isset($this->postID)) {
            return "WARN: Comment post to '<undefined>' failed.";
        } else if (count($this->commentFormData->errors) > 0) {
            return "WARN: Comment post to '{$this->post->title}' failed.";
        } else {
            return "INFO: Comment posted to '{$this->post->title}' " .
                   "by {$this->commentFormData->name}";
        }
    }

    public function getCommentEmailBody()
    {
        if (! isset($this->postID)) {
            $postURL = '<undefined>';
        } else {
            $postURL = $this->config->getPostURL($this->postID);
        }

        $errors = var_export($this->commentFormData->errors, TRUE);

        // Comment details.
        date_default_timezone_set($this->config->timezone);
        $date = var_export(strftime('%Y-%m-%d %H:%M:%S %Z',
                           time()), TRUE);
        $name = var_export($this->commentFormData->name, TRUE);
        $email = var_export($this->commentFormData->email, TRUE);
        $url = var_export($this->commentFormData->url, TRUE);
        $cookies = var_export($_COOKIE, TRUE);
        $text = $this->commentFormData->text;

        // Prepare email body.
        $body = <<<BODY
Comment: {$this->commentFormData->text}

Post URL: $postURL

Post ID: $this->postID

Date: $date
Name: {$this->commentFormData->name}
Email: {$this->commentFormData->email}
URL: {$this->commentFormData->url}

POPO comment:

<?php
\$comments[] = \$comment = new PBComment();
\$comment->disabled = FALSE;
\$comment->date = $date;
\$comment->name = $name;
\$comment->email = $email;
\$comment->url = $url;
\$comment->authority = FALSE;
\$comment->text = <<<HTML
<p>
$text
</p>
?>

Errors: $errors

Remote address: {$_SERVER['REMOTE_ADDR']}

User agent: {$_SERVER['HTTP_USER_AGENT']}

Cookies: $cookies
BODY;
    return $body;
    }
}
?>
