<?php

#
#  VAT.php
#
#  Created by Jonathon Wardman on 20-07-2009.
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
 * HMRC VAT API client.  Extends the functionality provided by the
 * GovTalk class to build and parse HMRC VAT submissions.  This class only
 * supports V2 of the HMRC VAT internet filing system.  The php-govtalk
 * base class needs including externally in order to use this extention.
 *
 * The IRmark validation scheme is currently not fully supported by this class.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcVat extends GovTalk {

 /* General IRenvelope related variables. */

	/**
	 * VAT number of the company this return is on the behalf of.
	 *
	 * @var string
	 */
	private $_vatRegNumber;
	
 /* System / internal variables. */

	/**
	 * Flag indicating if the IRmark should be generated for outgoing XML.
	 *
	 * @var boolean
	 */
	private $_generateIRmark = false;

 /* Magic methods. */

	/**
	 * Instance constructor. Contains a hard-coded CH XMLGW URL and additional
	 * schema location.  Adds a channel route identifying the use of this
	 * extension.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 * @param string $service The service to use ('tpvs', 'vsips', or 'live').
	 */
	public function __construct($govTalkSenderId, $govTalkPassword, $service = 'live') {

		switch ($service) {
			case 'tpvs':
				parent::__construct('https://www.tpvs.hmrc.gov.uk/HMRC/VATDEC', $govTalkSenderId, $govTalkPassword);
				$this->setTestFlag(true);
			break;
			case 'vsips':
				parent::__construct('https://secure.dev.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
				$this->setTestFlag(true);
			break;
			default:
				parent::__construct('https://secure.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
			break;
		}
		
		$this->setSchemaLocation('http://www.govtalk.gov.uk/taxation/vat/vatdeclaration/2/VATDeclarationRequest-v2-1.xsd', false);

		$this->setMessageAuthentication('clear');
		$this->addChannelRoute('http://blogs.fubra.com/php-govtalk/extensions/hmrc/vat/', 'php-govtalk HMRC VAT extension', '0.1');

	}

 /* Public methods. */

	/**
	 * Sets the VAT number to be used for this return submission (the number of
	 * the company for which this return contains data).
	 *
	 * @param string $vatNumber The number to use in the submission.
	 */
	public function setVatNumber($vatNumber) {
	
		if (preg_match('/^(GB)?(\d{9,12})$/', $vatNumber, $vatNumberChunks)) {
			$this->_vatRegNumber = $vatNumber;
			$this->addMessageKey('VATRegNo', $vatNumber);
		} else {
			return false;
		}
	
	}
	
	/**
	 * Turns the IRmark generator on or off (by default the IRmark generator is
	 * turned off). When it's switched off no IRmark element will be sent with
	 * requests to HMRC.
	 *
	 * @param boolean $flag True to turn on IRmark generator, false to turn it off.
	 */
	public function setIRmarkGeneration($flag) {
	
		if (is_bool($flag)) {
			$this->_generateIRmark = $flag;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Submits a VAT declaration request.
	 *
	 * This method supports final returns using the final argument.
	 *
	 * @param string $vatNumber The VAT number of the company this return is for.
	 * @param string $returnPeriod The period ID this return is for (in the format YYYY-MM).
	 * @param string $senderCapacity The capacity this return is being submitted under (Agent, Trust, Company, etc.).
	 * @param float $vatOutput VAT due on outputs (box 1).
	 * @param float $vatECAcq VAT due on EC acquisitions (box 2)..
	 * @param float $vatReclaimedInput VAT reclaimed on inputs (box 4).
	 * @param float $netOutput Net sales and outputs (box 6).
	 * @param float $newInput Net purchases and inputs (box 7).
	 * @param float $netECSupply Net EC supplies (box 8).
	 * @param float $netECAcq Net EC acquisitions (box 9).
	 * @param float $totalVat Total VAT (box 3). If this value is not specified then it will be calculated as box 1 + box 2. May be skipped by passing null.
	 * @param float $netVat Net VAT (box 5). If this value is not specified then it will be calculated as the absolute difference between box 5 and box 4. May be skipped by passing null.
	 * @param boolean $finalReturn Flag indicating if this return is a final VAT return.
	 * @return mixed An array of 'endpoint', 'interval' and 'correlationid' on success, or false on failure.
	 */
	public function declarationRequest($vatNumber, $returnPeriod, $senderCapacity, $vatOutput, $vatECAcq, $vatReclaimedInput, $netOutput, $netInput, $netECSupply, $netECAcq, $totalVat = null, $netVat = null, $finalReturn = false) {
	
		$vatNumber = trim(str_replace(' ', '', $vatNumber));
		if (preg_match('/^(GB)?(\d{9,12})$/', $vatNumber)) { # VAT number
			$this->setVatNumber($vatNumber);
			if (preg_match('/^\d{4}-\d{2}$/', $returnPeriod)) { # VAT period
				$validCapacities = array('Individual', 'Company', 'Agent',
				                         'Bureau', 'Partnership', 'Trust',
				                         'Employer', 'Government', 'Acting in Capacity',
				                         'Other');
				if (in_array($senderCapacity, $validCapacities)) {
					if (is_numeric($vatOutput) && is_numeric($vatECAcq) && is_numeric($vatReclaimedInput) && is_numeric($netOutput) && is_numeric($netInput) && is_numeric($netECSupply) && is_numeric($netECAcq)) {
				
	 // Set the message envelope bits and pieces for this request...
						$this->setMessageClass('HMRC-VAT-DEC');
						$this->setMessageQualifier('request');
						$this->setMessageFunction('submit');
				
	 // Build message body...
						$package = new XMLWriter();
						$package->openMemory();
						$package->setIndent(true);
						$package->startElement('IRenvelope');
							$package->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/vat/vatdeclaration/2');
							$package->startElement('IRheader');
								$package->startElement('Keys');
									$package->startElement('Key');
										$package->writeAttribute('Type', 'VATRegNo');
										$package->text($this->_vatRegNumber);
									$package->endElement(); # Key
								$package->endElement(); # Keys
								$package->writeElement('PeriodID', $returnPeriod);
								$package->writeElement('DefaultCurrency', 'GBP');
								if ($this->_generateIRmark === true) {
									$package->startElement('IRmark');
										$package->writeAttribute('Type', 'generic');
										$package->text('IRmark+Token');
									$package->endElement(); # IRmark
								}
								$package->writeElement('Sender', $senderCapacity);
							$package->endElement(); # IRheader
							$package->startElement('VATDeclarationRequest');
								if ($finalReturn === true) {
									$package->writeAttribute('finalReturn', 'yes');
								}
								$package->writeElement('VATDueOnOutputs', sprintf('%.2f', $vatOutput));
								$package->writeElement('VATDueOnECAcquisitions', sprintf('%.2f', $vatECAcq));
								if ($totalVat === null) {
									$totalVat = $vatOutput + $vatECAcq;
								}
								$package->writeElement('TotalVAT', sprintf('%.2f', $totalVat));
								$package->writeElement('VATReclaimedOnInputs', sprintf('%.2f', $vatReclaimedInput));
								if ($netVat === null) {
									$netVat = abs($totalVat - $vatReclaimedInput);
								}
								if ($netVat < 0) {
								   return false;
								}
								$package->writeElement('NetVAT', sprintf('%.2f', $netVat));
								$package->writeElement('NetSalesAndOutputs', floor($netOutput));
								$package->writeElement('NetPurchasesAndInputs', floor($netInput));
								$package->writeElement('NetECSupplies', floor($netECSupply));
								$package->writeElement('NetECAcquisitions', floor($netECAcq));
							$package->endElement(); # VATDeclarationRequest
						$package->endElement(); # IRenvelope

	 // Generate IRmark and add it to the body...
						$bodyText = $package->outputMemory();
						if ($this->_generateIRmark === true) {
							$irMark = base64_encode($this->_generateIRMark($bodyText));
							$bodyText = str_replace('IRmark+Token', $irMark, $bodyText);
						}

	 // Send the message and deal with the response...
						$this->setMessageBody($bodyText);
						if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
							$returnable = $this->getResponseEndpoint();
							$returnable['correlationid'] = $this->getResponseCorrelationId();
							return $returnable;
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
		
		} else {
			return false;
		}
	
	}
	
	/**
	 * Polls the Gateway for a submission response / error following a VAT
	 * declaration request. By default the correlation ID from the last response
	 * is used for the polling, but this can be over-ridden by supplying a
	 * correlation ID. The correlation ID can be skipped by passing a null value.
	 *
	 * If the resource is still pending this method will return the same array
	 * as declarationRequest() -- 'endpoint', 'interval' and 'correlationid' --
	 * if not then it'll return something more useful (and as yet undocumented).
	 *
	 * @param string $correlationId The correlation ID of the resource to poll. Can be skipped with a null value.
	 * @param string $pollUrl The URL of the Gateway to poll.
	 * @return mixed
	 */
	public function declarationResponsePoll($correlationId = null, $pollUrl = null) {
	
		if ($correlationId === null) {
			$correlationId = $this->getResponseCorrelationId();
		}

		if ($this->setMessageCorrelationId($correlationId)) {
			if ($pollUrl !== null) {
				$this->setGovTalkServer($pollUrl);
			}
			$this->setMessageClass('HMRC-VAT-DEC');
			$this->setMessageQualifier('poll');
			$this->setMessageFunction('submit');
			$this->resetMessageKeys();
			$this->setMessageBody('');
			if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
//var_dump($this->_fullResponseObject);
				$messageQualifier = (string) $this->_fullResponseObject->Header->MessageDetails->Qualifier;
				if ($messageQualifier == 'response') {
				
					$successResponse = $this->_fullResponseObject->Body->SuccessResponse;
				
					$responseMessage = array();
					foreach ($successResponse->Message AS $message) {
						$responseMessage[] = (string) $message;
					}
					$responseAcceptedTime = strtotime($successResponse->AcceptedTime);
					
					$declarationResponse = $successResponse->ResponseData->VATDeclarationResponse;
					$declarationPeriodId = (string) $declarationResponse->Header->PeriodId;
					$declarationPeriodStart = strtotime($declarationResponse->Header->PeriodStartDate);
					$declarationPeriodEnd = strtotime($declarationResponse->Header->PeriodEndDate);
					
               $paymentDueDate = strtotime($declarationResponse->Body->PaymentDueDate);
               $receiptTimestamp = strtotime($declarationResponse->Body->ReceiptTimestamp);
               
               $paymentNarrative = (string) $declarationResponse->Body->PaymentNotification->Narrative;
               $paymentNetVat = (string) $declarationResponse->Body->PaymentNotification->NetVAT;
               
					$paymentNotifcation = $successResponse->ResponseData->VATDeclarationResponse->Body->PaymentNotification;
					if (isset($paymentNotifcation->NilPaymentIndicator)) {

					} else if (isset($paymentNotifcation->RepaymentIndicator)) {

					} else if (isset($paymentNotifcation->DirectDebitPaymentStatus)) {

					} else if (isset($paymentNotifcation->PaymentRequest)) {

					}
// page 64
				} else if ($messageQualifier == 'acknowledgement') {
					$returnable = $this->getResponseEndpoint();
					$returnable['correlationid'] = $this->getResponseCorrelationId();
					return $returnable;
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
	 * Generates an IRmark hash from the given XML string for use in the IRmark
	 * node inside the message body.  The string passed must contain one IRmark
	 * element containing the string IRmark (ie. <IRmark>IRmark</IRmark>) or the
	 * function will fail.
	 *
	 * @param $xmlString string The XML to generate the IRmark hash from.
	 * @return string The IRmark hash.
	 */
	private function _generateIRMark($xmlString) {
	
		if (is_string($xmlString)) {
			$xmlString = preg_replace('/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/', '', $xmlString, -1, $matchCount);
			if ($matchCount == 1) {
				$xmlDom = new DOMDocument;
				$xmlDom->loadXML('<Body xmlns="http://www.govtalk.gov.uk/CM/envelope">'."\n".$xmlString.'</Body>');
				return sha1($xmlDom->documentElement->C14N(), true);
			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}

}