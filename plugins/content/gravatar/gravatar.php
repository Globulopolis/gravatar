<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.gravatar
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Gravatar plugin.
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.gravatar
 * @since       1.5
 */
class PlgContentGravatar extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/**
	 * Displays the voting area if in an article
	 *
	 * @param   string   $context  The context of the content being passed to the plugin
	 * @param   object   &$row     The article object
	 * @param   object   &$params  The article params
	 * @param   integer  $page     The 'page' number
	 *
	 * @return  mixed  html string containing code for the votes if in com_content else boolean false
	 */
	public function onContentBeforeDisplay($context, &$row, &$params, $page=0) {
		// Do not run in backend
		if (!JFactory::getApplication()->isSite()) {
			die();
		}

		$user = JFactory::getUser($row->created_by);
		$lang = JFactory::getLanguage();
		$lang_code = substr($lang->getTag(), 0, 2);
		$email_hash = md5(JString::strtolower($user->get('email')));
		$data_format = 'json';
		$profile = $this->getProfile($email_hash, $data_format);

		if ($data_format == 'json') {
			$profile_obj = json_decode($profile);
		}
echo '<pre>';
		print_r($profile_obj);
		$html = '<a href="'.$this->getGravatarServer($lang_code).$email_hash.'" rel="nofollow"><img src="'.$this->buildAvatarUrl($email_hash, $this->params->get('thumb_size')).'" border="0" /></a>';
echo '</pre>';
		return $html;
	}

	/**
	 * Build URL for user avatar
	 *
	 * @param   string   $email_hash  The email address hash
	 * @param   integer  $size        Size in pixels, defaults to 80px [ 1 - 2048 ]
	 * @param   string   $imageset    Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
	 * @param   string   $rating      Maximum rating (inclusive) [ g | pg | r | x ]
	 *
	 * @return  string   String containing URL
	 */
	protected function buildAvatarUrl($email_hash, $size=80, $imageset='mm', $rating='g') {
		$url = $this->getGravatarServer().'avatar/'.$email_hash.'?size='.$size.'&default='.urlencode($imageset).'&rating='.$rating;

		return $url;
	}

	/**
	 * Get a user profile
	 *
	 * @param   string   $email_hash  The email address hash
	 * @param   string   $format      Data format [ json | xml | php | VCF/vCard | QR Code ]
	 *
	 * @return  mixed    String or binary
	 */
	protected function getProfile($email_hash, $format='') {
		$lang = JFactory::getLanguage();
		$lang_code = substr($lang->getTag(), 0, 2);

		if ($format == 'json') {
			$url_format = '.json';
		} elseif ($format == 'xml') {
			$url_format = '.xml';
		} elseif ($format == 'vcf') {
			$url_format = '.vcf';
		} elseif ($format == 'qr') {
			$url_format = '.qr';
		} else {
			$url_format = '';
		}

		$url = $this->getGravatarServer($lang_code).$email_hash.$url_format;
		$result = $this->getRemoteData($url);

		return $result->body;
	}

	/**
	 * Get remote data from Gravatar server
	 *
	 * @param   string   $url          Complete url
	 * @param   array    $headers      An array of name-value pairs to include in the header of the request.
	 * @param   integer  $timeout      Read timeout in seconds.
	 * @param   string   $transport    Adapter (string) or queue of adapters (array) to use
	 *
	 * @return  JHttpResponse
	 */
	protected function getRemoteData($url, array $headers=null, $timeout=30, $transport='curl') {
		$options = new JRegistry;

		if (!is_array($headers)) {
			// If we're not set up an user-agent we get a 403 error.
			$headers = array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:26.0) Gecko/20100101 Firefox/26.0'
			);
		}

		$http = JHttpFactory::getHttp($options, $transport);
		$response = $http->get($url, $headers, $timeout);

		return $response;
	}

	protected function getGravatarServer($lang_code='en') {
		$scheme = JURI::getInstance()->getScheme();

		if ($scheme == 'http') {
			$url = 'http://'.$lang_code.'.gravatar.com/';
		} else {
			$url = 'https://'.$lang_code.'.secure.gravatar.com/';
		}

		return $url;
	}
}
