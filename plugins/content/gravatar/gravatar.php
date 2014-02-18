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

		// Support Joomla 2.x
		if (substr(JPlatform::getShortVersion(), 0, 2) == 11) {
			$this->loadLanguage();

			JHtml::script(JURI::base().'plugins/content/gravatar/assets/js/jquery-1.11.0.min.js');
		} else {
			JHtml::_('jquery.framework');
		}

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
				<a href="'.$profile_link.'" rel="nofollow" class="gravatar-profile-avatar hasTooltip" title="'.JText::_('PLG_GRAVATAR_LINK_PROFILE_VIEW').'"><img src="'.$this->buildAvatarUrl($email_hash, $this->params->get('thumb_size')).'" border="0" /></a>
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
								$html .= '<span class="gravatar-linked-im aim"><a href="aim:goim?screenname='.$im->value.'" rel="nofollow">'.ucfirst($im->type).'</a>: '.$im->value.'</span>';
							} elseif ($im->type == 'skype') {
								$html .= '<span class="gravatar-linked-im skype"><a href="skype:'.$im->value.'" rel="nofollow">'.ucfirst($im->type).'</a>: '.$im->value.'</span>';
							} elseif ($im->type == 'icq') {
								$html .= '<span class="gravatar-linked-im icq"><a href="icq:message?uin='.$im->value.'" rel="nofollow">'.ucfirst($im->type).'</a>: '.$im->value.'</span>';
							} elseif ($im->type == 'msn') {
								$html .= '<span class="gravatar-linked-im msn"><a href="msnim:chat?contact='.$im->value.'@" rel="nofollow">'.ucfirst($im->type).'</a>: '.$im->value.'</span>';
							} elseif ($im->type == 'yahoo') {
								$html .= '<span class="gravatar-linked-im yahoo"><a href="ymsgr:sendim?'.$im->value.'" rel="nofollow">'.ucfirst($im->type).'</a>: '.$im->value.'</span>';
							} elseif ($im->type == 'gtalk') {
								$html .= '<span class="gravatar-linked-im gtalk"><a href="xmpp:'.$im->value.'@gmail.com" rel="nofollow">'.ucfirst($im->type).'</a>: '.$im->value.'</span>';
							} else {
								$html .= '<a href="#" class="'.$im->type.'" rel="nofollow">'.ucfirst($im->type).'</a>';
							}
						}
					}

					if (isset($profile_item->emails)) {
						foreach ($profile_item->emails as $email) {
							$html .= '<span class="gravatar-linked-emails"><a href="mailto:'.$email->value.'" rel="nofollow">'.$email->value.'</a></span>';
						}
					}

					if (isset($profile_item->phoneNumbers)) {
						foreach ($profile_item->phoneNumbers as $phone) {
							$html .= '<span class="gravatar-linked-tel">'.JText::sprintf(JText::sprintf('PLG_GRAVATAR_PROFILE_PHONES', JText::_('PLG_GRAVATAR_PROFILE_PHONES_'.strtoupper($phone->type))), JText::_('PLG_GRAVATAR_PROFILE_PHONES_'.strtoupper($phone->type))).$phone->value.'</span>';
						}
					}

				$html .= '</div>
				<div class="right-col">
					<div class="gravatar-avatar-big">
						<div class="gravatar-photo-big">
							<img src="'.$this->buildAvatarUrl($email_hash, $this->params->get('photo_size')).'" />
						</div>
					</div>';

					if (isset($profile_item->urls) && count($profile_item->urls) > 0) {
						$html .= '<h3>'.JText::_('PLG_GRAVATAR_PROFILE_WEBSITES').'</h3>
						<ul class="gravatar-linked-web">';

						foreach ($profile_item->urls as $url) {
							$html .= '<li>
								<a href="'.$url->value.'" rel="nofollow" target="_blank"><img src="'.JURI::getInstance()->getScheme().'://s.wordpress.com/mshots/v1/'.urlencode($url->value).'?w=360" width="180" /><span>'.ucfirst($url->title).'</span></a>
							</li>';
						}
					}

						$html .= '</ul>
					</div>
				<div class="clear"></div>
				<div class="buttons"><button class="btn btn-primary cmd-hide">'.JText::_('JHIDE').'</button></div>
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
		$response = $this->getRemoteData($url, array(
			'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:26.0) Gecko/20100101 Firefox/26.0'
		), 15, array('curl', 'socket'));

		if ($response === false || $response->code != 200) {
			return;
		}

		return $response->body;
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
	protected function getRemoteData($url, array $headers=null, $timeout=30, $transport='socket') {
		$options = new JRegistry;

		$http = JHttpFactory::getHttp($options, $transport);

		try {
			$response = $http->get($url, $headers, $timeout);
		} catch (Exception $e) {
			return false;
		}

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
