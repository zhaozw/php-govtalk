<?php

#
#  GovTalk.php
#
#  Created by Jonathon Wardman on 14-07-2009.
#  Copyright 2009, Fubra Limited. All rights reserved.
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  You may obtain a copy of the License at:
#  http://www.gnu.org/licenses/gpl-3.0.txt
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.

/**
 * GovTalk API Client -- Builds, validates, sends, receives and validates
 * GovTalk messages for use with the UK government's GovTalk messaging system
 * (http://www.govtalk.gov.uk/). A generic wrapper designed to be extended for
 * use with the more specific interfaces provided by various government
 * departments. Generates valid GovTalk envelopes for agreed version 2.0.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class GovTalk {

 /* Server related variables. */
 
	/**
	 * GovTalk server.
	 *
	 * @var string
	 */
	private $_govTalkServer;
	/**
	 * GovTalk sender ID.
	 *
	 * @var string
	 */
	protected $_govTalkSenderId;
	/**
	 * GovTalk sender password.
	 *
	 * @var string
	 */
	protected $_govTalkPassword;

 /* General envelope related variables. */
 
	/**
	 * Additional XSI SchemaLocation URL.  Default is null, no additional schema.
	 *
	 * @var string
	 */
	private $_additionalXsiSchemaLocation = null;
	/**
	 * GovTalk test flag.  Default is 0, a real message.
	 *
	 * @var string
	 */
	private $_govTalkTest = '0';
	/**
	 * Body of the message to be sent.
	 *
	 * @var mixed Can either be of type XMLWriter, or a string.
	 */
	private $_messageBody;

 /* MessageDetails related variables */

	/**
	 * GovTalk message Class.
	 *
	 * @var string
	 */
	private $_messageClass;
	/**
	 * GovTalk message Qualifier.
	 *
	 * @var string
	 */
	private $_messageQualifier;
	/**
	 * GovTalk message Function.  Default is null, no specified function.
	 *
	 * @var string
	 */
	private $_messageFunction = null;
	/**
	 * GovTalk message CorrelationID.  Default is null, no correlation ID.
	 *
	 * @var string
	 */
	private $_messageCorrelationId = null;
	
 /* SenderDetails related variables. */
	
	/**
	 * GovTalk SenderDetail EmailAddress.  Default is null, no email address.
	 *
	 * @var string
	 */
	private $_senderEmailAddress = null;
	/**
	 * GovTalk message authentication type.
	 *
	 * @var string
	 */
	private $_messageAuthType;
	
 /* Keys related variables. */
 
	/**
	 * GovTalk keys array.
	 *
	 * @var array
	 */
	private $_govTalkKeys = array();
	
 /* Channel routing related variables. */
 
	/**
	 * GovTalk message channel routing array.
	 *
	 * @var array
	 */
	private $_messageChannelRouting = array();

 /* Full request/response data variables. */
 
	/**
	 * Full request data in string format (raw XML).
	 *
	 * @var string
	 */
	protected $_fullRequestString;
	/**
	 * Full return data in string format (raw XML).
	 *
	 * @var string
	 */
	protected $_fullResponseString;
	/**
	 * Full return data in object format (SimpleXML).
	 *
	 * @var string
	 */
	protected $_fullResponseObject;

 /* System / internal variables. */

	/**
	 * Transaction ID of the last message sent.
	 *
	 * @var string
	 */
	private $_lastTransactionId = null;

 /* Magic methods. */

	/**
	 * Instance constructor.
	 *
	 * @param string $govTalkServer GovTalk server.
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 */
	public function __construct($govTalkServer, $govTalkSenderId, $govTalkPassword) {

		$this->_govTalkServer = $govTalkServer;
		$this->_govTalkSenderId = $govTalkSenderId;
		$this->_govTalkPassword = $govTalkPassword;

	}
	
 /* Public methods. */
 
 /* Logical / operational / conditional methods. */
 
	/**
	 * Tests if a response has errors.  Should be checked before further
	 * operations are carried out on the returned object.
	 *
	 * @return boolean True if errors are present, false if not.
	 */
	public function responseHasErrors() {
	
		if (isset($this->_fullResponseObject)) {
			if (isset($this->_fullResponseObject->GovTalkDetails->GovTalkErrors)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}

 /* System / internal get methods. */

	/**
	 * Returns the transaction ID used in the last message sent.
	 *
	 * @return string Transaction ID.
	 */
	public function getLastTransactionId() {

		return $this->_lastTransactionId;

	}

	/**
	 * Returns the full XML request from the last Gateway request, if there is
	 * one.
	 *
	 * @return mixed The full text request from the Gateway, or false if this isn't set.
	 */
	public function getFullXMLRequest() {

		if (isset($this->_fullRequestString)) {
			return $this->_fullRequestString;
		} else {
			return false;
		}

	}
	
	/**
	 * Returns the full XML response from the last Gateway request, if there is
	 * one.
	 *
	 * @return mixed The full text response from the Gateway, or false if this isn't set.
	 */
	public function getFullXMLResponse() {
	
		if (isset($this->_fullResponseString)) {
			return $this->_fullResponseString;
		} else {
			return false;
		}
	
	}
	
 /* Response data get methods */

	/**
	 * Returns the Gateway response message qualifier of the last response
	 * received, if there is one.
	 *
	 * @return integer The response qualifier, or false if there is no response.
	 */
	public function getResponseQualifier() {

		if (isset($this->_fullResponseObject)) {
			return (string) $this->_fullResponseObject->Header->MessageDetails->Qualifer;
		} else {
			return false;
		}

	}

	/**
	 * Returns the Gateway timestamp of the last response received, if there is
	 * one.
	 *
	 * @return integer The Gateway timestamp as a unix timestamp, or false if this isn't set.
	 */
	public function getGatewayTimestamp() {
	
		if (isset($this->_fullResponseObject)) {
			return strtotime((string) $this->_fullResponseObject->Header->MessageDetails->GatewayTimestamp);
		} else {
			return false;
		}
	
	}
	
	/**
	 * Returns the contents of the response Body section, removing all GovTalk
	 * Message Envelope wrappers, as a SimpleXML object.
	 *
	 * @return mixed The message body as a SimpleXML object, or false if this isn't set.
	 */
	public function getResponseBody() {
	
		if (isset($this->_fullResponseObject)) {
			return $this->_fullResponseObject->Body;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Returns an array of errors, if any are present.  Errors can be 'fatal',
	 * 'recoverable', 'business' or 'warning'.  If no errors are found this
	 * function will return false.
	 *
	 * @return mixed Array of errors, or false if there are no errors.
	 */
	public function getResponseErrors() {

		if ($this->responseHasErrors()) {
			$errorArray = array('fatal' => array(),
			                    'recoverable' => array(),
			                    'recoverable' => array(),
			                    'business' => array(),
			                    'warning' => array());
			foreach ($this->_fullResponseObject->GovTalkDetails->GovTalkErrors->Error AS $responseError) {
				$errorDetails = array('number' => (string) $responseError->Number,
				                      'text' => (string) $responseError->Text);
				if (isset($responseError->Location) && (string) $responseError->Location !== '') {
					$errorDetails['location'] = (string) $responseError->Location;
				}
				$errorArray[(string) $responseError->Type][] = $errorDetails;
			}
			return $errorArray;
		} else {
			return false;
		}

	}

 /* General envelope related set methods. */

	/**
	 * An additional SchemaLocation for use in the GovTalk headers.  This URL
	 * should be the location of an additional xsd defining the body segment.
	 *
	 * @param string $schemaLocation URL location of additional xsd.
	 * @return boolean True if the URL is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setSchemaLocation($schemaLocation) {

		if (preg_match('/^https?:\/\/[\w-.]+\.gov\.uk/', $schemaLocation)) {
			$this->_additionalXsiSchemaLocation = $schemaLocation;
		} else {
			return false;
		}

	}

	/**
	 * Sets the test flag.  A flag value of true tells the Gateway this message
	 * is a test, false (default) tells it this is a live message.
	 *
	 * @param boolean $testFlag The value to set the test flag to.
	 * @return boolean True if the flag is set successfully, false otherwise.
	 */
	public function setTestFlag($testFlag) {
	
		if (is_bool($testFlag)) {
			if ($testFlag === true) {
				$this->_govTalkTest = '1';
			} else {
				$this->_govTalkTest = '0';
			}
		} else {
			return false;
		}

	}

	/**
	 * Sets the message body. Message body can be either of type XMLWriter, or a
	 * static string.  The message body will be included between the Body tags
	 * of the GovTalk envelope just as it's set and therefore must be valid XML.
	 *
	 * @param mixed $messageBody The XML body of the GovTalk message.
	 * @return boolean True if the body is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageBody($messageBody) {

		if (is_string($messageBody) || is_a($messageBody, 'XMLWriter')) {
			$this->_messageBody = $messageBody;
			return true;
		} else {
			return false;
		}

	}

 /* MessageDetails related set methods. */

	/**
	 * Sets the message Class for use in MessageDetails header.
	 *
	 * @param string $messageClass The class to set.
	 * @return boolean True if the Class is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageClass($messageClass) {

		$messageClassLength = strlen($messageClass);
		if (($messageClassLength > 4) && ($messageClassLength < 32)) {
			$this->_messageClass = $messageClass;
		} else {
			return false;
		}

	}

	/**
	 * Sets the message Qualifier for use in MessageDetails header.  The
	 * Qualifier may be one of 'request', 'acknowledgement', 'response', 'poll'
	 * or 'error'. Any other values will not be set and will return false.
	 *
	 * @param string $messageQualifier The qualifier to set.
	 * @return boolean True if the Qualifier is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageQualifier($messageQualifier) {

		$messageQualifier = strtolower($messageQualifier);
		switch ($messageQualifier) {
			case 'request':
			case 'acknowledgement':
			case 'reponse':
			case 'poll':
			case 'error':
				$this->_messageQualifier = $messageQualifier;
				return true;
			break;
			default:
				return false;
			break;
		}

	}
	
	/**
	 * Sets the message Function for use in MessageDetails header.
	 *
	 * @param string $messageFunction The function to set.
	 * @return boolean True if the Function is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageFunction($messageFunction) {
	
	 // TODO: Limit the possible values for Function.
		$this->_messageFunction = $messageFunction;
		return true;

	}
	
	/**
	 * Sets the message CorrelationID for use in MessageDetails header.
	 *
	 * @param string $messageCorrelationId The correlation ID to set.
	 * @return boolean True if the CorrelationID is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageCorrelationId($messageCorrelationId) {

	 // TODO: Track message correlation ids internally?
		if (strlen($messageCorrelationId) <= 32) {
			$this->_messageCorrelationId = $messageCorrelationId;
			return true;
		} else {
			return false;
		}

	}
	
 /* SenderDetails related set methods. */

	/**
	 * Sets the sender email address for use in SenderDetails header.  Note: the
	 * validation used when setting an email address here is that specified by
	 * the GovTalk 2.0 envelope specifcation and is somewhat limited.
	 *
	 * @param string $senderEmailAddress The email address to set.
	 * @return boolean True if the EmailAddress is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setSenderEmailAddress($senderEmailAddress) {
	
		if (preg_match('/[A-Za-z0-9\.\-_]{1,64}@[A-Za-z0-9\.\-_]{1,64}/', $senderEmailAddress)) {
			$this->_senderEmailAddress = $senderEmailAddress;
			return true;
		} else {
			return false;
		}

	}
	
	/**
	 * Sets the type of authentication to use for with the message.  The message
	 * type must be one of 'alternative', 'clear', 'MD5' or 'W3Csigned'. Other
	 * values will not be set and will return false.
	 *
	 * @param string $messageAuthType The type of authentication to set.
	 * @return boolean True if the authentication type is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageAuthentication($messageAuthType) {
	
		switch ($messageAuthType) {
			case 'alternative':
			case 'clear':
			case 'MD5':
			case 'W3Csigned':
				$this->_messageAuthType = $messageAuthType;
				return true;
			break;
			default:
				return false;
			break;
		}
	
	}
	
 /* Channel routing related methods. */

	/**
	 * Adds a channel routing element to the message.  Channel routes should be
	 * added in order by every application which the message has passed through
	 * prior to being sent to the Gateway.  php-govtalk does not support name
	 * elements in channel routing.  If not defined the timestamp element will
	 * automatically be added at the moment the route is added.  Any optional
	 * arguments may be skipped by passing null as that argument.
	 *
	 * Applications using php-govtalk should <i>always</i> add at least one
	 * additional channel route before sending a message to the Gateway.
	 *
	 * Note: php-govtalk will add itself as the last route in the chain.  This
	 * is to identify the library to the Gateway and to assist in tracking down
	 * issues caused by the library itself.
	 *
	 * @param string $uri The URI of the owner of the process being added to the route.
	 * @param string $softwareName The name of the software generating this route entry.
	 * @param string $softwareVersion The version number of the software generating this route entry.
	 * @param array $id An array of IDs (themselves array of 'type' and 'value') to add as array elements.
	 * @param string $timestamp A timestamp representing the time this route processed the message (xsd:dateTime format).
	 * @return boolean True if the route is valid and added, false if it's not valid (and therefore not added).
	 */
	public function addChannelRoute($uri, $softwareName = null, $softwareVersion = null, array $id = null, $timestamp = null) {
	
		if (is_string($uri)) {
			$newRoute = array('uri' => $uri);
			if ($softwareName !== null) {
				$newRoute['product'] = $softwareName;
			}
			if ($softwareVersion !== null) {
				$newRoute['version'] = $softwareVersion;
			}
			if ($id !== null && is_array($id)) {
				foreach ($id AS $idElement) {
					if (is_array($idElement)) {
						$newRoute['id'][] = $idElement;
					}
				}
			}
			if (($timestamp !== null) && ($parsedTimestamp = strtotime($timestamp))) {
				$newRoute['timestamp'] = date('c', $parsedTimestamp);
			} else {
				$newRoute['timestamp'] = date('c');
			}
			$this->_messageChannelRouting[] = $newRoute;
			return true;
		} else {
			return false;
		}
	
	}
	
 /* Keys related methods. */
	
	/**
	 * Add a key-value pair to the set of keys to be sent with the message as
	 * part of the GovTalkDetails element.
	 *
	 * @param string $keyType The key type (type attribute).
	 * @param string $keyValue The key value.
	 * @return boolean True if the key is valid and added, false if it's not valid (and therefore not added).
	 */
	public function addMessageKey($keyType, $keyValue) {
	
		if (is_string($keyType) && is_string($keyValue)) {
			$this->_govTalkKeys[] = array('type' => $keyType,
			                              'value' => $keyValue);
			return true;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Removed a key-value pair from the set of keys to be sent with the message
	 * as part of the GovTalkDetails element.
	 *
	 * Searching is done primarily on key type (type attribute) and all keys with
	 * a corresponding type attribute are deleted.  An optional value argument
	 * can be provided, and in these cases only keys with matching key type AND
	 * key value will be deleted (but again all keys which meeting these
	 * criterion will be deleted).
	 *
	 * @param string $keyType The key type (type attribute) to be deleted.
	 * @param string $keyValue The key value to be deleted.
	 * @return integer The number of keys deleted.
	 */
	public function deleteMessageKey($keyType, $keyValue = null) {
	
		$deletedCount = 0;
		$possibleMatches = array();
		foreach ($this->_govTalkKeys AS $arrayKey => $value) {
			if ($value['type'] == $keyType) {
				if (($keyValue !== null) && ($keyValue !== $value['value'])) {
					continue;
				}
				$deletedCount++;
				unset($this->_govTalkKeys[$arrayKey]);
			}
		}
		
		return $deletedCount;
	
	}
	
	/**
	 * Removes all GovTalkDetails Key key-value pairs.
	 *
	 * @return boolean Always returns true.
	 */
	public function resetMessageKeys() {
	
		$this->_govTalkKeys = array();
		return true;
	
	}
	
 /* Message sending related methods. */
 
	/**
	 * Sends the message currently stored in the object to the currently defined
	 * GovTalkServer and parses the response for use later.
	 *
	 * Note: the return value of this method does not reflect the success of the
	 * data transmitted to the Gateway, but that the message was transmitted
	 * correctly and that a response was received.  Applications must query
	 * the response methods to discover more informationa about the data recieved
	 * in the Gateway reply.
	 *
	 * @param mixed
	 * @return boolean True if the message was successfully submitted to the Gateway and a response was received, false if not.
	 */
	public function sendMessage() {
	
		if ($this->_fullRequestString = $this->_packageGovTalkEnvelope()) {
		   if (function_exists('curl_init')) {
				$curlHandle = curl_init($this->_govTalkServer);
				curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->_fullRequestString);
				$gatewayResponse = curl_exec($curlHandle);
				curl_close($curlHandle);
			} else {
				$streamOptions = array('http' => array('method' => 'POST',
				                                       'header' => 'Content-Type: text/xml',
				                                       'content' => $this->_fullRequestString));
				if ($fileHandle = @fopen($this->_govTalkServer, 'r', false, stream_context_create($streamOptions))) {
					$gatewayResponse = stream_get_contents($fileHandle);
				} else {
					return false;
				}
			}

			if ($gatewayResponse !== false) {
				$this->_fullResponseString = $gatewayResponse;
				$this->_fullResponseObject = simplexml_load_string($gatewayResponse);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}
	
 /* Protected methods. */
 
	/**
	 * This method is designed to be over-ridden by extending classes which
	 * require an alternative authentication algorithm.
	 *
	 * These methods should take the transaction ID as an argument and return
	 * an array of 'method' => the method string to use in IDAuthentication->
	 * Authentication->Method, 'token' => the token to use in IDAuthentication->
	 * Authentication->Value, or false on failure.
	 *
	 * @param string $transactionId Transaction ID to use generating the token.
	 * @return mixed The authentication array, or false on failure.
	 */
	protected function generateAlternativeAuthentication($transactionId) {
	
	   return false;
	
	}

	/**
	 * Packages the message currently stored in the object into a valid GovTalk
	 * envelope ready for sending.
	 *
	 * @return mixed The XML package (as a string) in GovTalk format, or false on failure.
	 */
	protected function _packageGovTalkEnvelope() {

	 // Firstly check we have everything we need to build the envelope...
		if (isset($this->_messageClass) && isset($this->_messageQualifier)) {
			if (isset($this->_govTalkSenderId) && isset($this->_govTalkPassword)) {
				if (isset($this->_messageAuthType)) {
	 // Generate the transaction ID...
				$transactionId = $this->_generateTransactionId();
					if (isset($this->_messageBody)) {
	 // Create the XML document (in memory)...
						$package = new XMLWriter();
						$package->openMemory();
						$package->setIndent(true);

	 // Packaging...
						$package->startElement('GovTalkMessage');
						$xsiSchemaLocation = 'http://www.govtalk.gov.uk/documents/envelope-v2-0.xsd';
						if ($this->_additionalXsiSchemaLocation !== null) {
							$xsiSchemaLocation .= ' '.$this->_additionalXsiSchemaLocation;
						}
						$package->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/CM/envelope');
						$package->writeAttributeNS('xsi', 'schemaLocation', 'http://www.w3.org/2001/XMLSchema-instance', $xsiSchemaLocation);
							$package->writeElement('EnvelopeVersion', '2.0');
							
	 // Header...
							$package->startElement('Header');
							
	 // Message details...
								$package->startElement('MessageDetails');
									$package->writeElement('Class', $this->_messageClass);
									$package->writeElement('Qualifier', $this->_messageQualifier);
									if ($this->_messageFunction !== null) {
										$package->writeElement('Function', $this->_messageFunction);
									}
									$package->writeElement('TransactionID', $transactionId);
									if ($this->_messageCorrelationId !== null) {
										$package->writeElement('CorrelationID', $this->_messageCorrelationId);
									}
									$package->writeElement('GatewayTest', $this->_govTalkTest);
								$package->endElement(); # MessageDetails
								
	 // Sender details...
								$package->startElement('SenderDetails');
								
	 // Authentication...
									$package->startElement('IDAuthentication');
										$package->writeElement('SenderID', $this->_govTalkSenderId);
										$package->startElement('Authentication');
										switch ($this->_messageAuthType) {
											case 'alternative':
												if ($authenticationArray = $this->generateAlternativeAuthentication($transactionId)) {
													$package->writeElement('Method', $authenticationArray['method']);
													$package->writeElement('Value', $authenticationArray['token']);
												} else {
													return false;
												}
											break;
											case 'clear':
												$package->writeElement('Method', 'clear');
												$package->writeElement('Value', $this->_govTalkPassword);
											break;
										}
										$package->endElement(); # Authentication
									$package->endElement(); # IDAuthentication
									if ($this->_senderEmailAddress !== null) {
										$package->writeElement('EmailAddress', $this->_senderEmailAddress);
									}
						
								$package->endElement(); # SenderDetails
						
							$package->endElement(); # Header
							
	 // GovTalk details...
							$package->startElement('GovTalkDetails');
							
	 // Keys...
								if (count($this->_govTalkKeys) > 0) {
									$package->startElement('Keys');
									foreach ($this->_govTalkKeys AS $keyPair) {
										$package->startElement('Key');
											$package->writeAttribute('type', $keyPair['type']);
											$package->text($keyPair['value']);
										$package->endElement(); # Key
									}
									$package->endElement(); # Keys
								}
							
	 // Channel routing...
								$package->startElement('ChannelRouting');
								$channelRouteArray = $this->_messageChannelRouting;
								$channelRouteArray[] = array('uri' => 'http://code.google.com/p/php-govtalk/',
								                             'product' => 'php-govtalk',
								                             'version' => '1.0',
								                             'timestamp' => date('c'));
								foreach ($channelRouteArray AS $channelRoute) {
									$package->startElement('Channel');
										$package->writeElement('URI', $channelRoute['uri']);
										if (array_key_exists('product', $channelRoute)) {
											$package->writeElement('Product', $channelRoute['product']);
										}
										if (array_key_exists('version', $channelRoute)) {
											$package->writeElement('Version', $channelRoute['version']);
										}
										if (array_key_exists('id', $channelRoute) && is_array($channelRoute['id'])) {
											foreach ($channelRoute['id'] AS $channelRouteId) {
												$package->startElement('ID');
													$package->writeAttribute('type', $channelRouteId['type']);
													$package->writeText($channelRouteId['value']);
												$package->endElement(); # ID
											}
										}
										$package->writeElement('Timestamp', $channelRoute['timestamp']);
									$package->endElement(); # Channel
								}
								$package->endElement(); # ChannelRouting
							
							$package->endElement(); # GovTalkDetails

	 // Body...
							$package->startElement('Body');
							if (is_string($this->_messageBody)) {
								$package->writeRaw("\n".trim($this->_messageBody)."\n");
							} else if (is_a($this->_messageBody, 'XMLWriter')) {
								$package->writeRaw("\n".trim($this->_messageBody->outputMemory())."\n");
							}
							$package->endElement(); # Body

						$package->endElement(); # GovTalkMessage

	 // Flush the buffer as the return of this function...
						return $package->flush();
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}

	}
	
 /* Private methods. */
 
	/**
	 * Generates the transaction ID required for GovTalk authentication. Although
	 * the GovTalk specifcation defines a valid transaction ID as [0-9A-F]{0,32}
	 * some government gateways using GovTalk only accept numeric transaction
	 * IDs. Therefore this implementation generates only a numeric transaction
	 * ID.
	 *
	 * @return string A numeric transaction ID valid for use in TransactionID.
	 */
	private function _generateTransactionId() {

		return str_replace('.', '', microtime(true));

	}

}