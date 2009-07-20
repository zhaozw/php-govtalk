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

	}

 /* Public methods. */

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
					$this->setMessageAuthentication('alternative');
					if ($bodyContent = $this->_generateNameSearchBody($companyName, $dataset)) {
						$this->setMessageBody($bodyContent);
						if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
							return $this->_parseCompanySearchResult($this->getResponseBody()->NameSearch);
						} else {
							return false;
						}
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
 
	/**
	 * Generates the body content for a simple company NameSearch.
	 *
	 * @param string $companyName The name of the company for which to search.
	 * @param string $dataset The dataset to search within ('LIVE', 'DISSOLVED', 'FORMER', 'PROPOSED').
	 * @return mixed The XML body content (in XMLWriter format), or false on failure.
	 */
	private function _generateNameSearchBody($companyName, $dataset = 'LIVE') {
	
		if (($companyName != '') && (strlen($companyName) < 161)) {
			$dataset = strtoupper($dataset);
			switch ($dataset) {
			   case 'LIVE':
			   case 'DISSOLVED':
			   case 'FORMER':
			   case 'PROPOSED':
					$package = new XMLWriter();
					$package->openMemory();
					$package->setIndent(true);
					$package->startElement('NameSearchRequest');
						$package->writeElement('CompanyName', $companyName);
						$package->writeElement('DataSet', $dataset);
					$package->endElement();
					return $package;
			   break;
			   default:
				   return false;
				break;
			}
		} else {
			return false;
		}
	
	}

}