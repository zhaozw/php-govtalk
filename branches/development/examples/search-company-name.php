<?php

	 // Include the Companies House module...
require_once('../CompaniesHouse.php');

	 // Companies house user ID and password...
$chUserId = 'XMLGatewayTestUserID';
$chPassword = 'XMLGatewayTestPassword';

if (isset($_GET['companyname'])) {

	 // Deal with form submission, do a CH search and print out a list...
	$companiesHouse = new CompaniesHouse($chUserId, $chPassword);
	if ($companyList = $companiesHouse->companyNameSearch($_GET['companyname'])) {

	 // Exact match...
		if (is_array($companyList['exact'])) {
			echo 'Exact name match: '.$companyList['exact']['name'].' ('.$companyList['exact']['number'].')';
		}
		
	 // Similar (including exact match)...
		echo '<ul>';
		foreach ($companyList['match'] AS $company) {
			echo '<li>'.$company['name'].' ('.$company['number'].')</li>';
		}
		echo '</ul>';
		
	} else {
	 // No companies found / error occured...
		echo 'No companies found for \''.$_GET['companyname'].'\'.';
	}
	
} else {

	 // First page visit, display the search box...
?>

<form action="" method="get">
	Search for company: <input name="companyname" type="text" /> <input type="submit" value="Search" />
</form>

<?php

}

?>