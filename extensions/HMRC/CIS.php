<?php

#
#  CIS.php
#
#  Created by Jonathon Wardman on 06-11-2009.
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
 * HMRC CIS API client.  Extends the functionality provided by the
 * GovTalk class to build and parse HMRC CIS submissions.  The php-govtalk
 * base class needs including externally in order to use this extention.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcCis extends GovTalk {

 /* General IRenvelope related variables. */

	/**
	 * Details of the agent sending the return declaration.
	 *
	 * @var array
	 */
	private $_agentDetails = array();

 /* System / internal variables. */

	/**
	 * Details of all the sub contractors to be submitted with this return.
	 *
	 * @var array
	 */
	private $_subContractorList = array();
	
	/**
	 * Flag indicating if all subcontractors' status has been checked.
	 *
	 * @var boolean
	 */
	private $_employmentStatusFlag;
	
	/**
	 * Flag indicating if all subcontractors have been verified with HMRC.
	 *
	 * @var boolean
	 */
	private $_verifcationFlag;
	
	/**
	 * Flag indicating if this return is a nil return.
	 *
	 * @var boolean
	 */
	private $_nilReturn = false;

	/**
	 * The Tax Office Number for this return.
	 *
	 * @var string
	 */
	private $_taxOfficeNumber;

	/**
	 * The Tax Office Reference for this return.
	 *
	 * @var string
	 */
	private $_taxOfficeReference;

	/**
	 * Flag indicating if the IRmark should be generated for outgoing XML.
	 *
	 * @var boolean
	 */
	private $_generateIRmark = true;

 /* Magic methods. */

	/**
	 * Instance constructor. Contains a hard-coded Gateway URLs and additional
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
				parent::__construct('https://www.tpvs.hmrc.gov.uk/new-cis/monthly_return', $govTalkSenderId, $govTalkPassword);
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

		$this->setMessageAuthentication('clear');
		$this->addChannelRoute('http://blogs.fubra.com/php-govtalk/extensions/hmrc/vat/', 'php-govtalk HMRC CIS extension', '0.1');

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
	 * Sets the Tax Office Number and Tax Office Reference of the person
	 * submitting the return.  These must be set in order for a retun to be
	 * submitted to HMRC.
	 *
	 * @param string $taxOfficeNumber The Tax Office Number.
	 * @param string $taxOfficeReference The Tax Office Reference.
	 * @return boolean True on success, false on failure.
	 */
	public function setTaxOfficeDetails($taxOfficeNumber, $taxOfficeReference) {
	
		if ($this->addMessageKey('TaxOfficeNumber', $taxOfficeNumber) && $this->addMessageKey('TaxOfficeReference', $taxOfficeReference)) {
			$this->_taxOfficeNumber = $taxOfficeNumber;
			$this->_taxOfficeReference = $taxOfficeReference;
			return true;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Defines this return as a nil return.  When the return is set as a nil
	 * return no subcontractor information should be set.  Therefore, if any
	 * subcontractor information exists when this method is called the method
	 * will return false.  Likewise, if this method has been set called and
	 * addSubContractor() is called subsiquently, addSubContractor() will fail.
	 *
	 * @return boolean True if this instance is set as a nil return, false if it cannot be set.
	 */
	public function setNilReturn() {
	
		if (count($this->_subContractorList) == 0) {
			$this->_nilReturn = true;
			return true;
		} else {
			return false;
		}
	
	}
	
	/**
	 * Adds a subcontractor to the list of subcontractors which will be used to
	 * build this return.  Subcontractors cannot be added if this return has
	 * already been set as a nil return.
	 *
	 * The subcontractor's details should be specified in an array as follows:
	 *   name => Array or string. If this element is a string it is assumed this is a company trading name, if it's an array it is assumed to be an individual's name and must be in the following format:
	 *     title => Contractor's title (Mr, Mrs, etc.)
	 *     forename => An array of the contractor's forename(s). Maximum of 2 forenames.
	 *     surname => Contractor's surname.
	 *  worksref => An optional reference.  Not used by HMRC. (Optional.)
	 *  higherrate => Boolean value indicating if the subcontractor is being paid at the higher rate of deduction. (Optional, defaults to false.)
	 *  utr => The subcontractor's UTR. This must be set if the higherrate flag is not set true.
	 *  crn => The subcontractor's Company Registration Number, if a company and known.
	 *  nino => The subcontractor's National Insurance Number, if an individual and known.
	 *  verifcation => The subcontractor's verifcation number, should be supplied if known when the higherrate flag is set to true.
	 *  totalpayments => The total value of payments made to this subcontractor.
	 *  materialcost => The direct cost of materials.
	 *  totaldeducted => The total value of deductions taken from this subcontractor's payments.
	 *
	 * @param array $subContractorDetails An array containing the details of the sub-contractor (see above).
	 * @param boolean $employmentStatus Flag indicating if the employment status of this subcontractor has "been considered and payments have not been made under contracts of employment".
	 * @param boolean $verified Flag indicating if this contractor "has either been verified with HM Revenue & Customs, or has been included in previous CIS return in this, or the previous two tax years".
	 * @return mixed The ID of the subcontrator added (base 0), or false if the subcontractor could not be added.
	 **/
	public function addSubContractor(array $subContractorDetails, $employmentStatus, $verified) {
	
		if ($this->_nilReturn === false) {
		
			if (is_bool($employmentStatus) && is_bool($verified)) {
				$newSubContractor = array();
				if ($this->_employmentStatusFlag !== false) {
					$this->_employmentStatusFlag = $employmentStatus;
				}
				if ($this->_verifcationFlag !== false) {
					$this->_verifcationFlag = $verified;
				}
		
	 // Contractor name, also controls some other requirements...
				if (isset($subContractorDetails['name'])) {
					if (is_array($subContractorDetails['name'])) {
						if (isset($subContractorDetails['name']['forename']) && isset($subContractorDetails['name']['surname'])) {
							$newSubContractor['Name'] = array();
							if (!is_array($subContractorDetails['name']['forename'])) {
								$subContractorDetails['name']['forename'][] = $subContractorDetails['name']['forename'];
							}
							foreach ($subContractorDetails['name']['forename'] AS $forenameElement) {
								$forenameLength = strlen($forenameElement);
								if (($forenameLength > 0) && ($forenameLength < 36) && preg_match('/[A-Za-z][A-Za-z\'\-]*/', $forenameElement)) {
									$newSubContractor['Name']['Fore'][] = $forenameElement;
								}
							}
							$surnameLength = strlen($subContractorDetails['name']['surname']);
							if (($surnameLength > 0) && ($surnameLength < 36) && preg_match('/[A-Za-z0-9 ,\.\(\)\/&\-\']+/', $subContractorDetails['name']['surname'])) {
								$newSubContractor['Name']['Sur'] = $subContractorDetails['name']['surname'];
							} else {
								return false;
							}
						} else {
							return false;
						}
						if (isset($subContractorDetails['name']['title']) && preg_match('/[A-Za-z][A-Za-z\'\-]*/', $subContractorDetails['name']['title'])) {
							$newSubContractor['Name']['Ttl'] = $subContractorDetails['name']['title'];
						}
					} else {
						$companyNameLength = strlen($subContractorDetails['name']);
						if (($companyNameLength < 57) && preg_match('/\S.*/', $subContractorDetails['name'])) {
							$newSubContractor['TradingName'] = $subContractorDetails['name'];
	 // CRN...
							if (isset($subContractorDetails['crn']) && preg_match('/[A-Za-z]{2}[0-9]{1,6}|[0-9]{1,8}/', $subContractorDetails['crn'])) {
								$newSubContractor['CRN'] = $subContractorDetails['crn'];
							}
						} else {
							return false;
						}
					}
				} else {
					return false;
				}
				
	 // NINO...
				if (isset($subContractorDetails['nino']) && preg_match('/[ABCEGHJKLMNOPRSTWXYZ][ABCEGHJKLMNPRSTWXYZ][0-9]{6}[A-D ]/', $subContractorDetails['nino'])) {
					$newSubContractor['NINO'] = $subContractorDetails['nino'];
				}

	 // Unmatched rate...
				if (isset($subContractorDetails['higherrate'])) {
					if ($subContractorDetails['higherrate'] === true) {
						$newSubContractor['UnmatchedRate'] = 'yes';
					}
				}
	 // UTR...
				if (isset($subContractorDetails['utr']) && preg_match('/[0-9]{10}/', $subContractorDetails['utr'])) {
					$newSubContractor['UTR'] = $subContractorDetails['utr'];
				} else {
					if (!isset($newSubContractor['UnmatchedRate'])) {
						return false;
					}
				}
				
	 // Total payments made...
				if (isset($subContractorDetails['totalpayments']) && is_numeric($subContractorDetails['totalpayments']) && ($subContractorDetails['totalpayments'] >= 0) && ($subContractorDetails['totalpayments'] <= 99999999)) {
					$newSubContractor['TotalPayments'] = sprintf('%.2f', round($subContractorDetails['totalpayments']));
				} else {
					return false;
				}
	 // Cost of materials...
				if (isset($subContractorDetails['materialcost']) && is_numeric($subContractorDetails['materialcost']) && ($subContractorDetails['materialcost'] >= 0) && ($subContractorDetails['materialcost'] <= 99999999)) {
					$newSubContractor['CostOfMaterials'] = sprintf('%.2f', round($subContractorDetails['materialcost']));
				} else {
					return false;
				}
	 // Total amount deducted...
				if (isset($subContractorDetails['totaldeducted']) && is_numeric($subContractorDetails['totaldeducted']) && ($subContractorDetails['totaldeducted'] >= 0) && ($subContractorDetails['totaldeducted'] <= 99999999.99)) {
					$newSubContractor['TotalDeducted'] = sprintf('%.2f', $subContractorDetails['totaldeducted']);
				} else {
					return false;
				}

	 // Subcontractor verifcation number...
				if (isset($subContractorDetails['verifcation'])) {
					$subContractorDetails['verifcation'] = strtoupper($subContractorDetails['verifcation']);
					if (preg_match('/V[0-9]{10}[A-HJ-NP-Z]{0,2}/', $subContractorDetails['verifcation'])) {
						$newSubContractor['VerificationNumber'] = $subContractorDetails['verifcation'];
					}
				}
		
	 // Works reference...
				if (isset($subContractorDetails['worksref'])) {
					if (strlen($subContractorDetails['worksref']) < 21) {
						$newSubContractor['WorksRef'] = $subContractorDetails['worksref'];
					}
				}

				$this->_subContractorList[] = $newSubContractor;
				return (count($this->_subContractorList) - 1);

			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}
	
	/**
	 * Removes a subcontractor from the list of subcontractors which will be used
	 * to build this return.
	 */
	public function deleteSubContractor($subContractorId) {
	
		if (is_int($subContractorId)) {
			if (isset($this->_subContractorList[$subContractorId])) {
				unset($this->_subContractorList[$subContractorId]);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}
	
	/**
	 * Packages and sends a CIS300 monthly return using information set
	 * through previous calls to addSubContractor() and other methods in this
	 * class.
	 *
	 * @param string $returnPeriod The end date for this return (must be in the format YYYY-mm-05).
	 * @param string $contractorUtr Contractor's UTR.
	 * @param string $contractorAoRef Contractor's Accounts Office Reference Number.
	 * @param string $senderCapacity The capacity this return is being submitted under (Agent, Trust, Company, etc.).
	 */
	public function monthlyReturnRequest($returnPeriod, $contractorUtr, $contractorAoRef, $senderCapacity, $inactivity = false, $informationCorrect = true) {
	
		if ($informationCorrect === true) {
			if (isset($this->_taxOfficeNumber) && isset($this->_taxOfficeReference)) {

				if (preg_match('/^\d{4}-\d{2}-05$/', $returnPeriod)) { # Return period
					if ((is_numeric($contractorUtr) && (strlen($contractorUtr) == 10)) && preg_match('/[0-9]{3}P[A-Za-z][A-Za-z0-9]{8}/', $contractorAoRef)) { # UTR and AORef
						$validCapacities = array('Individual', 'Company', 'Agent',
						                         'Bureau', 'Partnership', 'Trust',
						                         'Government', 'Other');
						if (in_array($senderCapacity, $validCapacities)) {
	
	 // Set the message envelope bits and pieces for this request...
							$this->setMessageClass('IR-CIS-CIS300MR');
							$this->setMessageQualifier('request');
							$this->setMessageFunction('submit');
							$this->addTargetOrganisation('IR');
		
	 // Build message body...
							$package = new XMLWriter();
							$package->openMemory();
							$package->setIndent(true);
							$package->startElement('IRenvelope');
								$package->writeAttribute('xmlns', 'http://www.govtalk.gov.uk/taxation/CISreturn');
								$package->startElement('IRheader');
									$package->startElement('Keys');
										$package->startElement('Key');
											$package->writeAttribute('Type', 'TaxOfficeNumber');
											$package->text($this->_taxOfficeNumber);
										$package->endElement(); # Key
										$package->startElement('Key');
											$package->writeAttribute('Type', 'TaxOfficeReference');
											$package->text($this->_taxOfficeReference);
										$package->endElement(); # Key
									$package->endElement(); # Keys
									$package->writeElement('PeriodEnd', $returnPeriod);
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
								$package->startElement('CISreturn');
									$package->startElement('Contractor');
										$package->writeElement('UTR', $contractorUtr);
										$package->writeElement('AOref', $contractorAoRef);
									$package->endElement(); # Contractor
									if ($this->_nilReturn === true) {
										$package->writeElement('NilReturn', 'yes');
									} else {
										foreach ($this->_subContractorList AS $subContractor) {
											$package->startElement('Subcontractor');
												$package->writeRaw("\n".trim($this->_xmlPackageArray($subContractor)->outputMemory())."\n"); # Subcontractor
											$package->endElement(); # Subcontractor
										}
									}
									$package->startElement('Declarations');
										if ($this->_nilReturn !== true) {
											if ($this->_employmentStatusFlag === true) {
												$package->writeElement('EmploymentStatus', 'yes');
											} else {
												$package->writeElement('EmploymentStatus', 'no');
											}
											if ($this->_verifcationFlag === true) {
												$package->writeElement('Verification', 'yes');
											} else {
												$package->writeElement('Verification', 'no');
											}
										}
										$package->writeElement('InformationCorrect', 'yes');
										if ($inactivity === true) {
											$package->writeElement('Inactivity', 'yes');
										}
									$package->endElement(); # Declarations
								$package->endElement(); # CISreturn
							$package->endElement(); # IRenvelope

	 // Send the message and deal with the response...
							$this->setMessageBody($package);
return $this->_packageGovTalkEnvelope();
							if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
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

			preg_match('/<Body>(.*?)<\/Body>/', str_replace("\n", '�', $package), $matches);
			$packageBody = str_replace('�', "\n", $matches[1]);

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