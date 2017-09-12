<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

$client = new Client();

$request = array();
$request['ValidateAddressesRequest'] = array(
  'AddressToValidateList' => array(
    'AddressToValidate' => array()
  ),
  'ValidateAddressOptions' => array(
    'IncludeNumberOfSuffixes' => TRUE,
    'IncludeDefaultGeoLocation' => TRUE
  ),
  'CallerIdentification' => array(
    'CallerName' => 'GEO-6 bpost batch validation'
  )
);

if (isset($argv[1]) && file_exists($argv[1])) {
  if (($handle = fopen($argv[1], 'r')) !== FALSE) {
    while (($data = fgetcsv($handle, 1000)) !== FALSE) {
      $id = intval(trim($data[0])); echo $data[0].'|'.$id.PHP_EOL;
      $r = array(
        '@id' => $id,
        'PostalAddress' => array(
          'DeliveryPointLocation' => array(
            'StructuredDeliveryPointLocation' => array(
              'StreetName' => $data[2],
              'StreetNumber' => $data[1]
            )
          ),
          'PostalCodeMunicipality' => array(
            'StructuredPostalCodeMunicipality' => array(
              'PostalCode' => $data[3],
              'MunicipalityName' => $data[4]
            )
          )
        ),
        'DeliveringCountryISOCode' => 'BE',
        'DispatchingCountryISOCode' => 'BE'
      );
      $request['ValidateAddressesRequest']['AddressToValidateList']['AddressToValidate'][] = $r;

      if (($id % 200) === 0) {
        validate_exec($client, $request);
        break;
      }
    }
    fclose($handle);

    //validate_exec($client, $request);
  }
} else {
  trigger_error(sprintf('File "%s" does not exists!', $argv[1]), E_USER_ERROR);
}

/*
 *
 */
function validate_exec($client, $request) {
  $fp = fopen('data/result.csv', 'w');

  try {
    $response = $client->request('POST', 'https://webservices-pub.bpost.be/ws/ExternalMailingAddressProofingCSREST_v1/address/validateAddresses', [
        'json' => $request
    ]);
    $json = json_decode((string)$response->getBody()); //print_r($json);
    foreach ($json->ValidateAddressesResponse->ValidatedAddressResultList->ValidatedAddressResult as $i => $r) {
      $request_data = $request['ValidateAddressesRequest']['AddressToValidateList']['AddressToValidate'][$i];
      $response_data = $r->ValidatedAddressList->ValidatedAddress[0];
      $data = array(
        $request_data['@id'],
        $request_data['PostalAddress']['DeliveryPointLocation']['StructuredDeliveryPointLocation']['StreetNumber'],
        $request_data['PostalAddress']['DeliveryPointLocation']['StructuredDeliveryPointLocation']['StreetName'],
        $request_data['PostalAddress']['PostalCodeMunicipality']['StructuredPostalCodeMunicipality']['PostalCode'],
        $request_data['PostalAddress']['PostalCodeMunicipality']['StructuredPostalCodeMunicipality']['MunicipalityName'],
        $response_data->PostalAddress->StructuredDeliveryPointLocation->StreetNumber,
        $response_data->PostalAddress->StructuredDeliveryPointLocation->StreetName,
        $response_data->PostalAddress->StructuredPostalCodeMunicipality->PostalCode,
        $response_data->PostalAddress->StructuredPostalCodeMunicipality->MunicipalityName,
        $response_data->AddressLanguage,
        $response_data->NumberOfSuffix,
        $response_data->ServicePointDetail->GeographicalLocationInfo->GeographicalLocation->Longitude->Value,
        $response_data->ServicePointDetail->GeographicalLocationInfo->GeographicalLocation->Latitude->Value
      );

      fputcsv($fp, $data);
    }
  } catch (ClientException $e) {
      echo Psr7\str($e->getRequest());
      echo Psr7\str($e->getResponse());
  }

  fclose($fp);
}

exit();
