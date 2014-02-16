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
 * @since       3.2
 */
class PlgContentGravatar extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.2
	 */
	protected $autoloadLanguage = true;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   3.2
	 */
	public function __construct(&$subject, $config = array()) {
		parent::__construct($subject, $config);

		JHtml::_('jquery.framework');
		JHtml::script(JURI::base().'plugins/content/gravatar/assets/js/gravatar.js');
		JHtml::stylesheet(JURI::base().'plugins/content/gravatar/assets/css/gravatar.css');
	}

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

			if (is_object($profile_obj)) {
				$profile_item = $profile_obj->entry[0];
			} else {
				$profile_item = array();
			}
		}

		$username = (isset($profile_item->displayName) && !empty($profile_item->displayName)) ? $profile_item->displayName : $user->name;
		$profile_link = $this->getGravatarServer($lang_code).$email_hash;

		if (isset($profile_item->name)) {
			$profile_name_obj = $profile_item->name;
			$first_name = isset($profile_name_obj->givenName) ? $profile_name_obj->givenName : $username;
			$last_name = isset($profile_name_obj->familyName) ? $profile_name_obj->familyName : '';
		} else {
			$first_name = $username;
			$last_name = '';
		}

		$location = isset($profile_item->currentLocation) ? $profile_item->currentLocation : '';
		$about = isset($profile_item->aboutMe) ? $profile_item->aboutMe : '';

		$html = '<div class="gravatar-profile">
			<div class="gravatar-profile-shortinfo">
				<a href="'.$profile_link.'" rel="nofollow" class="gravatar-profile-avatar"><img src="'.$this->buildAvatarUrl($email_hash, $this->params->get('thumb_size')).'" border="0" /></a>
				<span class="muted createdby">'.JText::sprintf('COM_CONTENT_WRITTEN_BY', '<a href="'.$profile_link.'" class="profile-link hasTooltip" title="'.JText::sprintf('PLG_GRAVATAR_LINK_TITLE', $username).'">'.$username.'</a>').'</span>
			</div>
			<div class="gravatar-profile-info">
				<div class="left-col">';
				if (!empty($first_name)) {
					$html .= '<h2 class="item-title"><a href="'.$profile_link.'" target="_blank" rel="nofollow">'.$first_name.'</a></h2>';
				}
					$html .= '<span class="location small">'.$location.'</span>
					<span class="about">'.$about.'</span>';

					if (isset($profile_item->accounts)) {
						$html .= '<h3>'.JText::_('PLG_GRAVATAR_PROFILE_ONLINE').'</h3>';

						foreach ($profile_item->accounts as $account) {
							$html .= '<a href="'.$account->url.'" class="gravatar-linked-profiles '.$account->shortname.'" rel="nofollow" target="_blank">'.$account->display.'</a>';
						}
					}

					if (isset($profile_item->ims)) {
						$html .= '<h3>'.JText::_('PLG_GRAVATAR_PROFILE_CONTACTS').'</h3>';

						foreach ($profile_item->ims as $im) {
							if ($im->type == 'aim') {
								$url = $im->type.':goim?screenname='.$im->value;
							} elseif ($im->type == 'skype') {
								$url = $im->type.':'.$im->value;
							} else {
								$url = '#';
							}

							$html .= '<a href="'.$url.'" class="gravatar-linked-im '.$im->type.'" rel="nofollow">'.ucfirst($im->type).'</a>';
						}
					}

				$html .= '</div>
				<div class="right-col">
					<div class="gravatar-photo-big"><img src="'.$this->buildAvatarUrl($email_hash, $this->params->get('photo_size')).'" /></div>';

					if (isset($profile_item->urls) && count($profile_item->urls) > 0) {
						$html .= '<h3>'.JText::_('PLG_GRAVATAR_PROFILE_WEBSITES').'</h3>';

						foreach ($profile_item->urls as $url) {
							$html .= '<a href="'.$url->value.'" class="gravatar-linked-web" rel="nofollow">'.ucfirst($im->title).'</a>';
						}
					}

				$html .= '</div>
			</div>
		</div>
		<div class="clear"></div>';

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
