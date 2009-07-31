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
 * GovTalk class to build and parse HMRC VAT submissions.  The php-govtalk
 * base class needs including externally in order to use this extention.
 *
 * @author Jonathon Wardman
 * @copyright 2009, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcVat extends GovTalk {

 /* Magic methods. */

	/**
	 * Instance constructor. Contains a hard-coded CH XMLGW URL and additional
	 * schema location.  Adds a channel route identifying the use of this
	 * extension.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 * @param boolean $vsips Flag indicating if this request should use the test (VSIPS) system.
	 */
	public function __construct($govTalkSenderId, $govTalkPassword, $vsips = false) {

		if ($vsips === true) {
			parent::__construct('https://secure.dev.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
			$this->setTestFlag(true);
		} else {
			parent::__construct('https://secure.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
		}

		$this->addChannelRoute('http://blogs.fubra.com/php-govtalk/extensions/hmrc/vat/', 'php-govtalk HMRC VAT extension', '0.1');

	}

 /* Public methods. */
 
	public function declarationRequest($vatNumber, $returnPeriod, $vatOutput, $vatECAcq, $vatReclaimedInput, $netOutput, $netInput, $netECSupply, $netECAcq, $totalVat = null, $netVat = null) {
	
		$vatNumber = trim(str_replace(' ', '', $vatNumber));
		if (preg_match('/^(GB)?(\d{9,12})$/', $vatNumber, $vatNumberChunks)) { # VAT number
			if (preg_match('/^\d{4}-\d{2}$/', $returnPeriod)) { # VAT period
				$this->addMessageKey('VATRegNo', $vatNumberChunks[2]);
				$this->setMessageQualifier('request');
				$this->setMessageClass('HMCE-VATDEC-ORG-VAT100-STD');
			} else {
				return false;
			}
		} else {
			return false;
		}
	
	}

}