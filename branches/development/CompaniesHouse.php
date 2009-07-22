<?php

#
#  CompaniesHouse.php
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

require_once('GovTalk.php');

/**
 * Companies House API client.  Extends the functionality provided by the
 * GovTalk class to build and parse Companies House data.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class CompaniesHouse extends GovTalk {

 /* Magic methods. */

	/**
	 * Instance constructor.  Contains a hard-coded CH XMLGW URL and additional
	 * schema location.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 */
	public function __construct($govTalkSenderId, $govTalkPassword) {
	
		parent::__construct('http://xmlgw.companieshouse.gov.uk/v1-0/xmlgw/Gateway', $govTalkSenderId, $govTalkPassword);
		$this->setSchemaLocation('http://xmlgw.companieshouse.gov.uk/v1-0/schema/Egov_ch-v2-0.xsd');
		$this->setMessageAuthentication('alternative');

	}

 /* Public methods. */

 /* Search methods. */
 
	/**
	 * Searches for companies with a registered name matching or similar to the
	 * given company name. Processes a company NameSearch and returns the results.
	 *
	 * @param string $companyName The name of the company for which to search.
	 * @param string $dataset The dataset to search within ('LIVE', 'DISSOLVED', 'FORMER', 'PROPOSED').
	 * @return mixed An array of companies found by the search, or false on failure.
	 */
	public function companyNameSearch($companyName, $dataset = 'LIVE') {
	
		if (($companyName != '') && (strlen($companyName) < 161)) {
			$dataset = strtoupper($dataset);
			switch ($dataset) {
			   case 'LIVE': case 'DISSOLVED': case 'FORMER': case 'PROPOSED':
			   
					$this->setMessageClass('NameSearch');
					$this->setMessageQualifier('request');

					$package = new XMLWriter();
					$package->openMemory();
					$package->setIndent(true);
					$package->startElement('NameSearchRequest');
						$package->writeElement('CompanyName', $companyName);
						$package->writeElement('DataSet', $dataset);
					$package->endElement();

					$this->setMessageBody($package);
					if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
						return $this->_parseCompanySearchResult($this->getResponseBody()->NameSearch);
					} else {
						return false;
					}
					
			   break;
			   default:
				   return false;
				break;
			}
		} else {
			return false;
		}
	
	}
	
	/**
	 * Searches for companies matching the given company number.  Processes a
	 * company NumberSearch and returns the results.
	 *
	 * @param string $companyNumber The number (or partial number) of the company for which to search.
	 * @param string $dataset The dataset to search within ('LIVE', 'DISSOLVED', 'FORMER', 'PROPOSED').
	 * @return mixed An array of companies found by the search, or false on failure.
	 */
	public function companyNumberSearch($companyNumber, $dataset = 'LIVE') {

		if (preg_match('/[A-Z0-9]{1,8}[*]{0,1}/', $companyNumber)) {
			$dataset = strtoupper($dataset);
			switch ($dataset) {
			   case 'LIVE': case 'DISSOLVED': case 'FORMER': case 'PROPOSED':

					$this->setMessageClass('NumberSearch');
					$this->setMessageQualifier('request');

					$package = new XMLWriter();
					$package->openMemory();
					$package->setIndent(true);
					$package->startElement('NumberSearchRequest');
						$package->writeElement('PartialCompanyNumber', $companyNumber);
						$package->writeElement('DataSet', $dataset);
					$package->endElement();

					$this->setMessageBody($package);
					if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
						return $this->_parseCompanySearchResult($this->getResponseBody()->NumberSearch);
					} else {
						return false;
					}

			   break;
			   default:
				   return false;
				break;
			}
		} else {
			return false;
		}

	}
	
	/**
	 * Searches for company officers matching the given criterion. Processes a
	 * company OfficerSearch and returns the results.
	 *
	 * The officer search may be restricted by officer type by passing one of the
	 * following as the second argument:
	 *   * DIS - Disqualified directors.
	 *   * LLP - Limited Liability partnerships.
	 *   * CUR - None of the above (default).
	 *   * EUR - SE and ES apointments only.
	 *
	 * @param string $officerSurname The surname of the officer for which to search.
	 * @param mixed $forename The forename(s) of the officer for which to search. If an array is passed all forenames will be used.
	 * @param string $officerType The type of officer for which to search (CUR, LLP, DIS, EUR).
	 * @param string $postTown The post town of the officer for which to search.
	 * @return mixed An array 'exact' => the match marked as nearest by CH, 'match' => all matches returned by CH, or false on failure.
	 */
	function companyOfficerSearch($officerSurname, $forename = null, $officerType = 'CUR', $postTown = null) {
	
		if ($officerSurname != '') {
			switch ($officerType) {
				case 'DIS': case 'LLP': case 'CUR': case 'EUR':
				
					$this->setMessageClass('OfficerSearch');
					$this->setMessageQualifier('request');
					
					$package = new XMLWriter();
					$package->openMemory();
					$package->setIndent(true);
					$package->startElement('OfficerSearchRequest');
						$package->writeElement('Surname', $officerSurname);
						$package->writeElement('OfficerType', $officerType);
						if ($forename !== null) {
							if (is_array($forename)) {
								$forenameCount = 0;
								foreach ($forename AS $singleForename) {
									$package->writeElement('Forename', $singleForename);
									if (++$forenameCount == 2) {
										break;
									}
								}
							} else {
								$package->writeElement('Forename', $forename);
							}
						}
						if ($postTown !== null) {
							$package->writeElement('PostTown', $postTown);
						}
					$package->endElement();
					
					$this->setMessageBody($package);
					if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
						$nearestOfficer = $possibleOfficers = array();
						$officerSearchBody = $this->getResponseBody()->OfficerSearch;
						foreach ($officerSearchBody->OfficerSearchItem AS $officerDetails) {
							$thisOfficerDetails = array('id' => (string) $officerDetails->PersonID,
							                            'title' => (string) $officerDetails->Title,
							                            'surname' => (string) $officerDetails->Surname,
							                            'forename' => (string) $officerDetails->Forename,
							                            'dob' => strtotime((string) $officerDetails->DOB),
							                            'posttown' => (string) $officerDetails->PostTown,
							                            'postcode' => (string) $officerDetails->Postcode);
	                  $possibleOfficers[] = $thisOfficerDetails;
							if (isset($officerDetails->SearchMatch) && ((string) $officerDetails->SearchMatch == 'NEAR')) {
								$nearestOfficer = $thisOfficerDetails;
							}
						}
						return array('exact' => $nearestOfficer,
						             'match' => $possibleOfficers);
					} else {
						return false;
					}
					
				break;
				default:
					return false;
				break;
			}
		} else {
			return false;
		}
	
	}
	
 /* Details methods. */
	
	/**
	 * Processes a company DetailsRequest and returns the results.
	 *
	 * @param string $companyNumber The number of the company for which to return details.
	 * @param boolean $mortgageTotals Flag indicating if mortgage totals should be returned (if available).
	 * @return mixed An array packed with lots of exciting company data, or false on failure.
	 */
	public function companyDetails($companyNumber, $mortgageTotals = false) {
	
		if (preg_match('/[A-Z0-9]{8,8}/', $companyNumber)) {

			$this->setMessageClass('CompanyDetails');
			$this->setMessageQualifier('request');
			
			$package = new XMLWriter();
			$package->openMemory();
			$package->setIndent(true);
			$package->startElement('CompanyDetailsRequest');
				$package->writeElement('CompanyNumber', $companyNumber);
				if ($mortgageTotals === true) {
					$package->writeElement('GiveMortTotals', '1');
				}
			$package->endElement();
			
			$this->setMessageBody($package);
			if ($this->sendMessage() && ($this->responseHasErrors() === false)) {

	 // Basic details...
				$companyDetailsBody = $this->getResponseBody()->CompanyDetails;
				$companyDetails = array('name' => (string) $companyDetailsBody->CompanyName,
				                        'number' => (string) $companyDetailsBody->CompanyNumber,
				                        'category' => (string) $companyDetailsBody->CompanyCategory,
				                        'status' => (string) $companyDetailsBody->CompanyStatus,
				                        'liquidation' => (string) $companyDetailsBody->InLiquidation,
				                        'branchinfo' => (string) $companyDetailsBody->HasBranchInfo,
				                        'appointments' => (string) $companyDetailsBody->HasAppointments);

	 // Dates...
				if (isset($companyDetailsBody->RegistrationDate)) {
					$companyDetails['registration_date'] = strtotime((string) $companyDetailsBody->RegistrationDate);
				}
				if (isset($companyDetailsBody->DissolutionDate)) {
					$companyDetails['dissolution_date'] = strtotime((string) $companyDetailsBody->DissolutionDate);
				}
				if (isset($companyDetailsBody->IncorporationDate)) {
					$companyDetails['incorporation_date'] = strtotime((string) $companyDetailsBody->IncorporationDate);
				}
				if (isset($companyDetailsBody->ClosureDate)) {
					$companyDetails['closure_date'] = strtotime((string) $companyDetailsBody->ClosureDate);
				}

	// Accounts and finance...
				if (isset($companyDetailsBody->Accounts)) {
					$companyDetails['accounts'] = array('overdue' => (string) $companyDetailsBody->Accounts->Overdue,
					                                    'document' => (string) $companyDetailsBody->Accounts->DocumentAvailable);
					if (isset($companyDetailsBody->Accounts->AccountRefDate)) {
						$companyDetails['accounts']['reference_date'] = (string) $companyDetailsBody->Accounts->AccountRefDate;
					}
					if (isset($companyDetailsBody->Accounts->NextDueDate)) {
						$companyDetails['accounts']['due_date'] = strtotime((string) $companyDetailsBody->Accounts->NextDueDate);
					}
					if (isset($companyDetailsBody->Accounts->LastMadeUpDate)) {
						$companyDetails['accounts']['last_madeup'] = strtotime((string) $companyDetailsBody->Accounts->LastMadeUpDate);
					}
					if (isset($companyDetailsBody->Accounts->AccountCategory)) {
						$companyDetails['accounts']['category'] = (string) $companyDetailsBody->Accounts->AccountCategory;
					}
				}
				if (isset($companyDetailsBody->Returns)) {
					$companyDetails['returns'] = array('overdue' => (string) $companyDetailsBody->Returns->Overdue,
					                                   'document' => (string) $companyDetailsBody->Returns->DocumentAvailable);
					if (isset($companyDetailsBody->Returns->NextDueDate)) {
						$companyDetails['returns']['due_date'] = strtotime((string) $companyDetailsBody->Returns->NextDueDate);
					}
					if (isset($companyDetailsBody->Returns->LastMadeUpDate)) {
						$companyDetails['returns']['last_madeup'] = strtotime((string) $companyDetailsBody->Returns->LastMadeUpDate);
					}
				}
				if (isset($companyDetailsBody->Mortgages)) {
					$companyDetails['mortgage'] = array('register' => (string) $companyDetailsBody->Mortgages->MortgageInd,
					                                    'charges' => (string) $companyDetailsBody->Mortgages->NumMortCharges,
					                                    'outstanding' => (string) $companyDetailsBody->Mortgages->NumMortOutstanding,
					                                    'part_satisfied' => (string) $companyDetailsBody->Mortgages->NumMortPartSatisfied,
					                                    'fully_satisfied' => (string) $companyDetailsBody->Mortgages->NumMortSatisfied);
				}

	 // Additional company details...
				if (isset($companyDetailsBody->PreviousNames)) {
					foreach ($companyDetailsBody->PreviousNames->CompanyName AS $previousName) {
						$companyDetails['previous_name'][] = (string) $previousName;
					}
				}
				foreach ($companyDetailsBody->RegAddress->AddressLine AS $addressLine) {
					$companyDetails['address'][] = (string) $addressLine;
				}
				foreach ($companyDetailsBody->SICCodes->SicText AS $sicItem) {
					$companyDetails['sic_code'][] = (string) $sicItem;
				}

				return $companyDetails;

			} else {
				return false;
			}

		} else {
			return false;
		}
	
	}
	
	/**
	 * Processes a company FilingHistoryRequest and returns the results.
	 *
	 * @param string $companyNumber The number of the company for which to return filing history.
	 * @param boolean $capitalDocs Flag indicating if capital documents should be returned (if available).
	 * @return mixed An array containing the filing history inclduing document keys, or false on failure.
	 */
	public function companyFilingHistory($companyNumber, $capitalDocs = false) {

		if (preg_match('/[A-Z0-9]{8,8}/', $companyNumber)) {

			$this->setMessageClass('FilingHistory');
			$this->setMessageQualifier('request');

			$package = new XMLWriter();
			$package->openMemory();
			$package->setIndent(true);
			$package->startElement('FilingHistoryRequest');
				$package->writeElement('CompanyNumber', $companyNumber);
				if ($capitalDocs === true) {
					$package->writeElement('CapitalDocInd', '1');
				}
			$package->endElement();

			$this->setMessageBody($package);
			if ($this->sendMessage() && ($this->responseHasErrors() === false)) {

				$filingHistoryBody = $this->getResponseBody()->FilingHistory;
				if (isset($filingHistoryBody->FHistItem)) {
					$filingHistory = array();
					foreach ($filingHistoryBody->FHistItem AS $historyItem) {
						$thisHistoryItem = array('date' => (string) $historyItem->DocumentDate,
						                         'type' => (string) $historyItem->FormType);
						foreach ($historyItem->DocumentDesc AS $documentDescription) {
							$thisHistoryItem['description'][] = (string) $documentDescription;
						}
						if (isset($historyItem->DocBeingScanned)) {
							$thisHistoryItem['pending'] = (string) $historyItem->DocBeingScanned;
						}
						if (isset($historyItem->ImageKey)) {
							$thisHistoryItem['key'] = (string) $historyItem->ImageKey;
						}
						$filingHistory[] = $thisHistoryItem;
					}
					return $filingHistory;
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
	 * Generates the token required to authenticate with the XML Gateway.  This
	 * function assumes the Gateway username and password have already been
	 * defined.  It over-rides the GovTalk class'
	 * _generateAlternativeAuthentication() method.
	 *
	 * @param string $transactionId Transaction ID to use generating the token.
	 * @return mixed The authentication array, or false on failure.
	 */
	protected function generateAlternativeAuthentication($transactionId) {

		if (is_numeric($transactionId)) {
			$authenticationArray = array('method' => 'CHMD5',
			                             'token' => md5($this->_govTalkSenderId.$this->_govTalkPassword.$transactionId));
			return $authenticationArray;
		} else {
			return false;
		}

	}
 
 /* Private methods. */
 
	/**
	 * Parses the partial output of a CompanySearch result into an array.
	 *
	 * @param string $companySearchBody The body of the CompanySearch response.
	 * @return mixed An array 'exact' => any match marked as exact by CH, 'match' => all matches returned by CH, or false on failure.
	 */
	private function _parseCompanySearchResult($companySearchBody) {
	
		if (is_object($companySearchBody) && is_a($companySearchBody, 'SimpleXMLElement')) {
			$exactCompany = $possibleCompanies = array();
			foreach ($companySearchBody->CoSearchItem AS $possibleCompany) {
				$thisCompanyDetails = array('name' => (string) $possibleCompany->CompanyName,
				                            'number' => (string) $possibleCompany->CompanyNumber);
				$possibleCompanies[] = $thisCompanyDetails;
				if (isset($possibleCompany->SearchMatch) && ((string) $possibleCompany->SearchMatch == 'EXACT')) {
					$exactCompany = $thisCompanyDetails;
				}
			}
			return array('exact' => $exactCompany,
			             'match' => $possibleCompanies);
		} else {
			return false;
		}
	
	}
	
}