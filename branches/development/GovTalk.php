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
 * Known limitations: No support for GovTalkDetails->Keys.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class GovTalk {

public function test() { var_dump($this->_packageGovTalkEnvelope()); }

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
	private $_govTalkSenderId;
	/**
	 * GovTalk sender password.
	 *
	 * @var string
	 */
	private $_govTalkPassword;

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
	 * GovTalk message Function.
	 *
	 * @var string
	 */
	private $_messageFunction = null;
	/**
	 * GovTalk message authentication type.
	 *
	 * @var string
	 */
	private $_messageAuthType;

	/**
	 * Body of the message to be sent.
	 *
	 * @var mixed Can either be of type XMLWriter, or a string.
	 */
	private $_messageBody;

	/**
	 * Additional XSI SchemaLocation URL.
	 *
	 * @var string
	 */
	private $_additionalXsiSchemaLocation = null;

	/**
	 * Transaction ID of the last message sent.
	 *
	 * @var string
	 */
	private $_lastTransactionId = null;

	/**
	 * Instance constructor.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 */
	public function __construct($govTalkSenderId, $govTalkPassword) {

		$this->_govTalkSenderId = $govTalkSenderId;
		$this->_govTalkPassword = $govTalkPassword;

	}

	/**
	 * Returns the transaction ID used in the last message sent.
	 *
	 * @return string Transaction ID.
	 */
	public function getLastTransactionId() {

		return $this->_lastTransactionId;

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

	}
	
	/**
	 * Sets the type of authentication to use for with the message.  The message
	 * type must be one of 'clear', 'CHMD5', 'MD5' or 'W3Csigned'.  Any other
	 * values will not be set and will return false.
	 *
	 * @param string $messageAuthType The type of authentication to set.
	 * @return boolean True if the authentication type is valid and set, false if it's invalid (and therefore not set).
	 */
	public function setMessageAuthentication($messageAuthType) {
	
		switch ($messageAuthType) {
			case 'clear':
			case 'CHMD5':
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
	 * Sends the message currently stored in the object to the currently defined
	 * GovTalkServer.
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
							$xsiSchemaLocation .= ' http://xmlgw.companieshouse.gov.uk/v1-0/schema/Egov_ch-v2-0.xsd';
						}
						$package->writeAttributeNS('xsi', 'schemaLocation', 'http://www.w3.org/2001/XMLSchema-instance', $xsiSchemaLocation);
							$package->writeElement('EnvelopeVersion', '1.0');
							
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
								$package->endElement(); # MessageDetails
								
	 // Sender details...
								$package->startElement('SenderDetails');
								
	 // Authentication...
								switch ($this->_messageAuthType) {
									case 'CHMD5':
										$authenticationToken = $this->_generateCHMD5Authentication($transactionId);
											$package->startElement('IDAuthentication');
												$package->writeElement('SenderID', $this->_govTalkSenderId);
												$package->startElement('Authentication');
													$package->writeElement('Method', 'CHMD5');
													$package->writeElement('Value', $authenticationToken);
												$package->endElement(); # Authentication
											$package->endElement(); # IDAuthentication
									break;
								}
						
								$package->endElement(); # SenderDetails
						
							$package->endElement(); # Header

	 // Body...
							$package->startElement('Body');
							if (is_string($this->_messageBody)) {
								$package->writeRaw($this->_messageBody);
							} else if (is_a($this->_messageBody, 'XMLWriter')) {
								$package->writeRaw($this->_messageBody->outputMemory());
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

	/**
	 * Generates the token required to authenticate with the XML Gateway.  This
	 * function assumes the Gateway username and password have already been
	 * defined.
	 *
	 * @param string $transactionId Transaction ID to use generating the token.
	 * @return mixed The authentication token, or false on failure.
	 */
	private function _generateCHMD5Authentication($transactionId) {

		if (is_numeric($transactionId)) {
			return md5($this->_govTalkSenderId.$this->_govTalkPassword.$transactionId);
		} else {
			return false;
		}

	}

}