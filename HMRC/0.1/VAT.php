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
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcVat extends GovTalk {

 /* General IRenvelope related variables. */

	/**
	 * Details of the agent sending the return declaration.
	 *
	 * @var string
	 */
	private $_agentDetails = array();
	
 /* System / internal variables. */

	/**
	 * Flag indicating if the IRmark should be generated for outgoing XML.
	 *
	 * @var boolean
	 */
	private $_generateIRmark = true;

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
	 * Sets details about the agent submitting the declaration.
	 *
	 * The agent company's address should be specified in the following format:
	 *   line => Array, each element containing a single line information.
	 *   postcode => The agent company's postcode.
	 *   country => The agent company's country. Defaults to England.
	 *
	 * The agent company's primary contact should be specified as follows:
	 *   name => Array, format as follows:
	 *     title => Contact's title (Mr, Mrs, etc.)
	 *     forename => Contact's forename.
	 *     surname => Contact's surname.
	 *   email => Contact's email address (optional).
	 *   telephone => Contact's telephone number (optional).
	 *   fax => Contact's fax number (optional).
	 *
	 * @param string $company The agent company's name.
	 * @param array $address The agent company's address in the format specified above.
	 * @param array $contact The agent company's key contact in the format specified above (optional, may be skipped with a null value).
	 * @param string $reference An identifier for the agent's own reference (optional).
	 */
	public function setAgentDetails($company, array $address, array $contact = null, $reference = null) {
	
		if (preg_match('/[A-Za-z0-9 &\'\(\)\*,\-\.\/]*/', $company)) {
			$this->_agentDetails['company'] = $company;
			$this->_agentDetails['address'] = $address;
			if (!isset($this->_agentDetails['address']['country'])) {
				$this->_agentDetails['address']['country'] = 'England';
			}
			if ($contact !== null) {
				$this->_agentDetails['contact'] = $contact;
			}
			if (($reference !== null) && preg_match('/[A-Za-z0-9 &\'\(\)\*,\-\.\/]*/', $reference)) {
				$this->_agentDetails['reference'] = $reference;
			}
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
	 * @param float $vatECAcq VAT due on EC acquisitions (box 2).
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
			$this->addMessageKey('VATRegNo', $vatNumber);
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
										$package->text($vatNumber);
									$package->endElement(); # Key
								$package->endElement(); # Keys
								$package->writeElement('PeriodID', $returnPeriod);
								if (count($this->_agentDetails) > 0) {
									$package->startElement('Agent');
										if (isset($this->_agentDetails['reference'])) {
											$package->writeElement('AgentID', $this->_agentDetails['reference']);
										}
										$package->writeElement('Company', $this->_agentDetails['company']);
										$package->startElement('Address');
											foreach ($this->_agentDetails['address']['line'] AS $line) {
												$package->writeElement('Line', $line);
											}
											$package->writeElement('PostCode', $this->_agentDetails['address']['postcode']);
											$package->writeElement('Country', $this->_agentDetails['address']['country']);
										$package->endElement(); # Address
										if (isset($this->_agentDetails['contact'])) {
											$package->startElement('Contact');
												$package->startElement('Name');
													$package->writeElement('Ttl', $this->_agentDetails['contact']['name']['title']);
													$package->writeElement('Fore', $this->_agentDetails['contact']['name']['forename']);
													$package->writeElement('Sur', $this->_agentDetails['contact']['name']['surname']);
												$package->endElement(); # Name
												if (isset($this->_agentDetails['contact']['email'])) {
													$package->writeElement('Email', $this->_agentDetails['contact']['email']);
												}
												if (isset($this->_agentDetails['contact']['telephone'])) {
													$package->writeElement('Telephone', $this->_agentDetails['contact']['telephone']);
												}
												if (isset($this->_agentDetails['contact']['fax'])) {
													$package->writeElement('Fax', $this->_agentDetails['contact']['fax']);
												}
											$package->endElement(); # Contact
										}
									$package->endElement(); # Agent
								}
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
						
	 // Send the message and deal with the response...
						$this->setMessageBody($package);
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
	 * if not then it'll return lots of useful information relating to the return
	 * and payment of any VAT due in the following array format:
	 *
	 *  message => an array of messages ('Thank you for your submission', etc.).
	 *  accept_time => the time the submission was accepted by the HMRC server.
	 *  period => an array of information relating to the period of the return:
	 *    id => the period ID.
	 *    start => the start date of the period.
	 *    end => the end date of the period.
	 *  payment => an array of information relating to the payment of the return:
	 *    narrative => a string representation of the payment (generated by HMRC)
	 *    netvat => the net value due following this return.
	 *    payment => an array of information relating to the method of payment:
	 *      method => the method to be used to pay any money due, options are:
	 *        - nilpayment: no payment is due.
	 *        - repayment: a repayment from HMRC is due.
	 *        - directdebit: payment will be taken by previous direct debit.
	 *        - payment: payment should be made by alternative means.
	 *      additional => additional information relating to this payment.
	 *
	 * @param string $correlationId The correlation ID of the resource to poll. Can be skipped with a null value.
	 * @param string $pollUrl The URL of the Gateway to poll.
	 * @return mixed An array of details relating to the return and payment, or false on failure.
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
			
				$messageQualifier = (string) $this->_fullResponseObject->Header->MessageDetails->Qualifier;
				if ($messageQualifier == 'response') {
				
					$successResponse = $this->_fullResponseObject->Body->SuccessResponse;
					
					if (isset($successResponse->IRmarkReceipt)) {
						$irMarkReceipt = (string) $successResponse->IRmarkReceipt->Message;
					}
					
					$responseMessage = array();
					foreach ($successResponse->Message AS $message) {
						$responseMessage[] = (string) $message;
					}
					$responseAcceptedTime = strtotime($successResponse->AcceptedTime);
					
					$declarationResponse = $successResponse->ResponseData->VATDeclarationResponse;
					$declarationPeriod = array('id' => (string) $declarationResponse->Header->VATPeriod->PeriodId,
					                           'start' => strtotime($declarationResponse->Header->VATPeriod->PeriodStartDate),
					                           'end' => strtotime($declarationResponse->Header->VATPeriod->PeriodEndDate));

					$paymentDueDate = strtotime($declarationResponse->Body->PaymentDueDate);

               $paymentDetails = array('narrative' => (string) $declarationResponse->Body->PaymentNotification->Narrative,
					                        'netvat' => (string) $declarationResponse->Body->PaymentNotification->NetVAT);
               
					$paymentNotifcation = $successResponse->ResponseData->VATDeclarationResponse->Body->PaymentNotification;
					if (isset($paymentNotifcation->NilPaymentIndicator)) {
						$paymentDetails['payment'] = array('method' => 'nilpayment', 'additional' => null);
					} else if (isset($paymentNotifcation->RepaymentIndicator)) {
						$paymentDetails['payment'] = array('method' => 'repayment', 'additional' => null);
					} else if (isset($paymentNotifcation->DirectDebitPaymentStatus)) {
						$paymentDetails['payment'] = array('method' => 'directdebit', 'additional' => strtotime($paymentNotifcation->DirectDebitPaymentStatus->CollectionDate));
					} else if (isset($paymentNotifcation->PaymentRequest)) {
						$paymentDetails['payment'] = array('method' => 'payment', 'additional' => (string) $paymentNotifcation->PaymentRequest->DirectDebitInstructionStatus);
					}
					
					return array('message' => $responseMessage,
					             'irmark' => $irMarkReceipt,
					             'accept_time' => $responseAcceptedTime,
					             'period' => $declarationPeriod,
					             'payment' => $paymentDetails);
					
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
	
 /* Protected methods. */

	/**
	 * Adds a valid IRmark to the given package.
	 *
	 * This function over-rides the packageDigest() function provided in the main
	 * php-govtalk class.
	 *
	 * @param string $package The package to add the IRmark to.
	 * @return string The new package after addition of the IRmark.
	 */
	protected function packageDigest($package) {
	
		if ($this->_generateIRmark === true) {
			$packageSimpleXML = simplexml_load_string($package);
			$packageNamespaces = $packageSimpleXML->getNamespaces();
			
			preg_match('/<Body>(.*?)<\/Body>/', str_replace("\n", '¬', $package), $matches);
			$packageBody = str_replace('¬', "\n", $matches[1]);
			
			$irMark = base64_encode($this->_generateIRMark($packageBody, $packageNamespaces));
			$package = str_replace('IRmark+Token', $irMark, $package);
		}
		
		return $package;
	
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
	private function _generateIRMark($xmlString, $namespaces = null) {
	
		if (is_string($xmlString)) {
			$xmlString = preg_replace('/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/', '', $xmlString, -1, $matchCount);
			if ($matchCount == 1) {
				$xmlDom = new DOMDocument;
				
				if ($namespaces !== null && is_array($namespaces)) {
					$namespaceString = array();
					foreach ($namespaces AS $key => $value) {
						if ($key !== '') {
							$namespaceString[] = 'xmlns:'.$key.'="'.$value.'"';
						} else {
							$namespaceString[] = 'xmlns="'.$value.'"';
						}
					}
					$bodyCompiled = '<Body '.implode(' ', $namespaceString).'>'.$xmlString.'</Body>';
				} else {
					$bodyCompiled = '<Body>'.$xmlString.'</Body>';
				}
				$xmlDom->loadXML($bodyCompiled);

				return sha1($xmlDom->documentElement->C14N(), true);

			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}

}