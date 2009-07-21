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
	 * Instance constructor.  Contains a hard-coded CH XMLGW URL.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 */
	public function __construct($govTalkSenderId, $govTalkPassword) {
	
		parent::__construct('http://xmlgw.companieshouse.gov.uk/v1-0/xmlgw/Gateway', $govTalkSenderId, $govTalkPassword);
		$this->setMessageAuthentication('alternative');

	}

 /* Public methods. */

 /* Search methods. */
 
	/**
	 * Processes a simple company NameSearch and returns the results.
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
	 * Processes a simple company NumberSearch and returns the results.
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
	
 /* Details methods. */
	
	/**
	 * Processes a company DetailsRequest and returns the results.
	 *
	 * @param string $companyNumber The number of the company for which to return details.
	 * @param boolean $mortTotals Flag indicating if mortgage totals should be returned (if available).
	 * @return mixed An array packed with lots of exciting company data, or false on failure.
	 */
	public function companyDetailsRequest($companyNumber, $mortTotals = true) {
	
		if (preg_match('/[A-Z0-9]{8,8}/', $companyNumber)) {

			$this->setMessageClass('CompanyDetails');
			$this->setMessageQualifier('request');
			
			$package = new XMLWriter();
			$package->openMemory();
			$package->setIndent(true);
			$package->startElement('CompanyDetailsRequest');
				$package->writeElement('CompanyNumber', $companyNumber);
				if ($mortTotals === true) {
					$package->writeElement('GiveMortTotals', '1');
				}
			$package->endElement();
			
			$this->setMessageBody($package);
			if ($this->sendMessage() && ($this->responseHasErrors() === false)) {

	 // Basic details...
				$companyDetailsBody = $this->getResponseBody();
				$companyDetails = array('name' => (string) $companyDetailsBody->CompanyDetails->CompanyName,
				                        'number' => (string) $companyDetailsBody->CompanyDetails->CompanyNumber,
				                        'category' => (string) $companyDetailsBody->CompanyDetails->CompanyCategory,
				                        'status' => (string) $companyDetailsBody->CompanyDetails->CompanyStatus,
				                        'liquidation' => (string) $companyDetailsBody->CompanyDetails->InLiquidation,
				                        'branchinfo' => (string) $companyDetailsBody->CompanyDetails->HasBranchInfo,
				                        'appointments' => (string) $companyDetailsBody->CompanyDetails->HasAppointments);

	 // Dates...
				if (isset($companyDetailsBody->CompanyDetails->RegistrationDate)) {
					$companyDetails['registration_date'] = strtotime((string) $companyDetailsBody->CompanyDetails->RegistrationDate);
				}
				if (isset($companyDetailsBody->CompanyDetails->DissolutionDate)) {
					$companyDetails['dissolution_date'] = strtotime((string) $companyDetailsBody->CompanyDetails->DissolutionDate);
				}
				if (isset($companyDetailsBody->CompanyDetails->IncorporationDate)) {
					$companyDetails['incorporation_date'] = strtotime((string) $companyDetailsBody->CompanyDetails->IncorporationDate);
				}
				if (isset($companyDetailsBody->CompanyDetails->ClosureDate)) {
					$companyDetails['closure_date'] = strtotime((string) $companyDetailsBody->CompanyDetails->ClosureDate);
				}

	// Accounts and finance...
				if (isset($companyDetailsBody->CompanyDetails->Accounts)) {
					$companyDetails['accounts'] = array('overdue' => (string) $companyDetailsBody->CompanyDetails->Accounts->Overdue,
					                                    'document' => (string) $companyDetailsBody->CompanyDetails->Accounts->DocumentAvailable);
					if (isset($companyDetailsBody->CompanyDetails->Accounts->AccountRefDate)) {
						$companyDetails['accounts']['reference_date'] = (string) $companyDetailsBody->CompanyDetails->Accounts->AccountRefDate;
					}
					if (isset($companyDetailsBody->CompanyDetails->Accounts->NextDueDate)) {
						$companyDetails['accounts']['due_date'] = strtotime((string) $companyDetailsBody->CompanyDetails->Accounts->NextDueDate);
					}
					if (isset($companyDetailsBody->CompanyDetails->Accounts->LastMadeUpDate)) {
						$companyDetails['accounts']['last_madeup'] = strtotime((string) $companyDetailsBody->CompanyDetails->Accounts->LastMadeUpDate);
					}
					if (isset($companyDetailsBody->CompanyDetails->Accounts->AccountCategory)) {
						$companyDetails['accounts']['category'] = (string) $companyDetailsBody->CompanyDetails->Accounts->AccountCategory;
					}
				}
				if (isset($companyDetailsBody->CompanyDetails->Returns)) {
					$companyDetails['returns'] = array('overdue' => (string) $companyDetailsBody->CompanyDetails->Returns->Overdue,
					                                   'document' => (string) $companyDetailsBody->CompanyDetails->Returns->DocumentAvailable);
					if (isset($companyDetailsBody->CompanyDetails->Returns->NextDueDate)) {
						$companyDetails['returns']['due_date'] = strtotime((string) $companyDetailsBody->CompanyDetails->Returns->NextDueDate);
					}
					if (isset($companyDetailsBody->CompanyDetails->Returns->LastMadeUpDate)) {
						$companyDetails['returns']['last_madeup'] = strtotime((string) $companyDetailsBody->CompanyDetails->Returns->LastMadeUpDate);
					}
				}
				if (isset($companyDetailsBody->CompanyDetails->Mortgages)) {
					$companyDetails['mortgage'] = array('register' => (string) $companyDetailsBody->CompanyDetails->Mortgages->MortgageInd,
					                                    'charges' => (string) $companyDetailsBody->CompanyDetails->Mortgages->NumMortCharges,
					                                    'outstanding' => (string) $companyDetailsBody->CompanyDetails->Mortgages->NumMortOutstanding,
					                                    'part_satisfied' => (string) $companyDetailsBody->CompanyDetails->Mortgages->NumMortPartSatisfied,
					                                    'fully_satisfied' => (string) $companyDetailsBody->CompanyDetails->Mortgages->NumMortSatisfied);
				}

	 // Additional company details...
				if (isset($companyDetailsBody->CompanyDetails->PreviousNames)) {
					foreach ($companyDetailsBody->CompanyDetails->PreviousNames->CompanyName AS $previousName) {
						$companyDetails['previous_name'][] = (string) $previousName;
					}
				}
				foreach ($companyDetailsBody->CompanyDetails->RegAddress->AddressLine AS $addressLine) {
					$companyDetails['address'][] = (string) $addressLine;
				}
				foreach ($companyDetailsBody->CompanyDetails->SICCodes->SicText AS $sicItem) {
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
	public function companyFilingHistoryRequest($companyNumber, $capitalDocs = true) {

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

	 // Basic details...
				$filingHistoryBody = $this->getResponseBody();
				if (isset($filingHistoryBody->FilingHistory->FHistItem)) {
					$filingHistory = array();
					foreach ($filingHistoryBody->FilingHistory->FHistItem AS $historyItem) {
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
				$possibleCompanies[] = array('name' => (string) $possibleCompany->CompanyName,
				                             'number' => (string) $possibleCompany->CompanyNumber);
				if (isset($possibleCompany->SearchMatch) && ((string) $possibleCompany->SearchMatch == 'EXACT')) {
					$exactCompany = array('name' => (string) $possibleCompany->CompanyName,
					                      'number' => (string) $possibleCompany->CompanyNumber);
				}
			}
			return array('exact' => $exactCompany,
			             'match' => $possibleCompanies);
		} else {
			return false;
		}
	
	}
	
}