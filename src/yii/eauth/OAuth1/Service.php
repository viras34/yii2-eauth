<?php
/**
 * OAuth1Service class file.
 *
 * @author Maxim Zemskov <nodge@yandex.ru>
 * @link http://github.com/Nodge/yii2-eauth/
 * @license http://www.opensource.org/licenses/bsd-license.php
 */

namespace yii\eauth\OAuth1;

use Yii;
use OAuth\Common\Exception\Exception as OAuthException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Consumer\Credentials;
use OAuth\OAuth1\Signature\Signature;
use yii\eauth\EAuth;
use yii\eauth\ErrorException;
use yii\eauth\IAuthService;
use yii\eauth\OAuthServiceBase;

/**
 * EOAuthService is a base class for all OAuth providers.
 *
 * @package application.extensions.eauth
 */
abstract class Service extends OAuthServiceBase implements IAuthService {

	/** @var ServiceProxy */
	protected $proxy;

	/**
	 * @var string OAuth2 client id.
	 */
	protected $key;

	/**
	 * @var string OAuth2 client secret key.
	 */
	protected $secret;

	/**
	 * @var array Provider options. Must contain the keys: request, authorize, access.
	 */
	protected $providerOptions = array(
		'request' => '',
		'authorize' => '',
		'access' => '',
	);

	/**
	 * Initialize the component.
	 *
	 * @param EAuth $component the component instance.
	 * @param array $options properties initialization.
	 */
//	public function init($component, $options = array()) {
//		parent::init($component, $options);
//	}

	/**
	 * @param string $key
	 */
	public function setKey($key) {
		$this->key = $key;
	}

	/**
	 * @param string $secret
	 */
	public function setSecret($secret) {
		$this->secret = $secret;
	}

	/**
	 * Authenticate the user.
	 *
	 * @return boolean whether user was successfuly authenticated.
	 * @throws ErrorException
	 */
	public function authenticate() {
		try {
			$storage = $this->getTokenStorage();
			$httpClient = $this->getHttpClient();
			$credentials = new Credentials($this->key, $this->secret, $this->getCallbackUrl());
			$signature = new Signature($credentials);
			$proxy = new ServiceProxy($credentials, $httpClient, $storage, $signature);
			$proxy->setService($this);
			$this->proxy = $proxy;

			if (!empty($_GET['oauth_token'])) {
				$token = $proxy->retrieveAccessToken();

				// This was a callback request, get the token now
				$proxy->requestAccessToken($_GET['oauth_token'], $_GET['oauth_verifier'], $token->getRequestTokenSecret());

				$this->authenticated = true;
			}
			else if ($proxy->hasValidAccessToken()) {
				$this->authenticated = true;
			}
			else {
				// extra request needed for oauth1 to request a request token :-)
				$token = $proxy->requestRequestToken();
				/** @var $url Uri */
				$url = $proxy->getAuthorizationUri(array('oauth_token' => $token->getRequestToken()));
				Yii::$app->getResponse()->redirect($url->getAbsoluteUri())->send();
			}
		}
		catch (OAuthException $e) {
			throw new ErrorException($e->getMessage(), $e->getCode(), 1, $e->getFile(), $e->getLine(), $e);
		}

		return $this->getIsAuthenticated();
	}

	/**
	 * @return string
	 */
	public function getRequestTokenEndpoint() {
		return $this->providerOptions['request'];
	}

	/**
	 * @return string
	 */
	public function getAuthorizationEndpoint() {
		return $this->providerOptions['authorize'];
	}

	/**
	 * @return string
	 */
	public function getAccessTokenEndpoint() {
		return $this->providerOptions['access'];
	}
}