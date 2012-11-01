<?php
/**
 * Comment form.
 *
 * This script displays comment form and processes submissions.
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

use susam\SCTK;

/**
 * Comment form.
 *
 * This class displays comment form and processes submissions.
 *
 * @category susam
 * @package popoblog
 * @author Susam Pal <susam@susam.in>
 * @copyright 2012 Susam Pal
 * @license http://www.gnu.org/licenses/gpl.html
 *          GNU General Public License version 3
 * @version 0.1
 */
class PBCommentForm
{
    public $postID;
    public $name;
    public $email;
    public $url;
    public $text;

    private $submit;
    private $index;

    public $errors;

    private $config;
    private $tmpl;

    public $processed;

    public function __construct($config, $tmpl)
    {
        $this->config = $config;
        $this->tmpl = $tmpl;

        $data = new PBDB($config);
        $this->index = $data->readIndex();

        $this->processed = FALSE;
        $this->errors = array();
    }
    
    public function readDataFromRequest()
    {
        $this->postID = SCTK::get($_POST, $this->config->commentParam, '');
        $this->name = SCTK::get($_POST, $this->config->commenterNameParam, '');
        $this->email = SCTK::get($_POST, $this->config->commenterEmailParam, '');
        $this->url = SCTK::get($_POST, $this->config->commenterURLParam, '');
        $this->text = SCTK::get($_POST, $this->config->commenterTextParam, '');
        $this->submit = SCTK::get($_POST, $this->config->submitFormParam, '');
    }

    public function readDataFromCookie()
    {
        $this->name =
            SCTK::get($_COOKIE, $this->config->commenterNameParam, '');

        $this->email =
            SCTK::get($_COOKIE, $this->config->commenterEmailParam, '');

        $this->url =
            SCTK::get($_COOKIE, $this->config->commenterURLParam, '');
    }

    public function setDataToCookie()
    {
        $expiry = time() + 86400 * 365 * 5;
        setcookie($this->config->commenterNameParam, $this->name, $expiry);
        setcookie($this->config->commenterEmailParam, $this->email, $expiry);
        setcookie($this->config->commenterURLParam, $this->url, $expiry);
    }

    public function validate()
    {

        if (! isset($this->index->posts[$this->postID])) {
            $this->errors[] = 'Post ID is invalid.';
        }

        if ($this->name == '') {
            $this->errors[] = 'Your name must be mentioned.';        
        }

        if ($this->email == '') {
            $this->errors[] = 'Your email address must be mentioned.';
        } else {
            if (preg_match('/^\S+@\S+\.\S+$/', $this->email) == 0) {
                $this->errors[] = 'Email address seems to be invalid.';
            }
        }

        if ($this->url != '') {
            if (preg_match('/^(http:\/\/)?[^\s\/]+\.\S+$/',
                           $this->url) == 0) {
                $this->errors[] = 'URL seems to be invalid.';
            }
        }

        if ($this->text == '') {
            $this->errors[] = 'You forgot to enter the comment.';
        }

        $this->processed = TRUE;
    }

}
?>
