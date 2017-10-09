<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

$options = getopt('', array('file:', 'start::'));

$start = (isset($options['start']) ? intval($options['start']) : 0);
$file = $options['file'];
$cursor = 1;

$client = new Client();

$source = array();

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

if (isset($file) && file_exists($file)) {
  $fname = pathinfo($file, PATHINFO_FILENAME);
  $dir = 'data/'.$fname;

  if (!file_exists($dir) || !is_dir($dir)) {
    mkdir($dir);
  }

  if ($start === 0) {
    echo sprintf('Clean directory "%s".', $dir).PHP_EOL;
    $glob = glob($dir.'/*');
    foreach ($glob as $g) {
      unlink($g);
    }
  }

  if (($handle = fopen($file, 'r')) !== FALSE) {
    while (($data = fgetcsv($handle, 1000)) !== FALSE) {
      if ($cursor < $start) {
        $cursor++;
        continue;
      }

      $id = $data[0];
      $source[$id] = $data;

      echo $cursor.' | '.$id.' | '.date('c').PHP_EOL;

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

      if (($cursor % 100) === 0) {
        validate_exec($dir, $client, $request, $source);

        $request['ValidateAddressesRequest']['AddressToValidateList']['AddressToValidate'] = array();
      }

      $cursor++;
    }
    fclose($handle);

    validate_exec($dir, $client, $request, $source);
  }
} else {
  trigger_error(sprintf('File "%s" does not exists!', $file), E_USER_ERROR);
}

/*
 *
 */
function validate_exec($directory, $client, $request, &$source) {
  $fp = fopen($directory.'/result.csv', 'a');
  $fp_error = fopen($directory.'/error.csv', 'a');

  $request_error = FALSE;

  try {
    //file_put_contents('data/request.csv', json_encode($request));
    $response = $client->request('POST', 'https://webservices-pub.bpost.be/ws/ExternalMailingAddressProofingCSREST_v1/address/validateAddresses', [
        'json' => $request
    ]);
    $json = json_decode((string)$response->getBody());
    foreach ($json->ValidateAddressesResponse->ValidatedAddressResultList->ValidatedAddressResult as $i => $r) {
      $request_data = $request['ValidateAddressesRequest']['AddressToValidateList']['AddressToValidate'][$i];
      $response_data = $r->ValidatedAddressList->ValidatedAddress[0];

      $errors = array();
      $warnings = array();

      if (isset($r->Error)) {
        foreach ($r->Error as $error) {
          switch ($error->ErrorSeverity) {
            case 'error':
              $errors[] = $error->ErrorCode;
              break;
            case 'warning':
              $warnings[] = $error->ErrorCode.': '.$error->ComponentRef;
              break;
            default:
              trigger_error(sprintf('ERROR [%s] : %s (%s)', $error->ErrorSeverity, $error->ErrorCode, $error->ComponentRef), E_USER_WARNING);
              break;
          }
        }
      }

      $data = $source[$request_data['@id']];

      if (!empty($errors)) {
        $data = array_merge($data, array(
          implode('; ', $errors)
        ));
        fputcsv($fp_error, $data);
      } else {
        $data = array_merge($data, array(
          (isset($response_data->PostalAddress->StructuredDeliveryPointLocation->StreetNumber) ? $response_data->PostalAddress->StructuredDeliveryPointLocation->StreetNumber : ''),
          (isset($response_data->PostalAddress->StructuredDeliveryPointLocation->StreetName) ? $response_data->PostalAddress->StructuredDeliveryPointLocation->StreetName : ''),
          (isset($response_data->PostalAddress->StructuredPostalCodeMunicipality->PostalCode) ? $response_data->PostalAddress->StructuredPostalCodeMunicipality->PostalCode : ''),
          (isset($response_data->PostalAddress->StructuredPostalCodeMunicipality->MunicipalityName) ? $response_data->PostalAddress->StructuredPostalCodeMunicipality->MunicipalityName : ''),
          (isset($response_data->AddressLanguage) ? $response_data->AddressLanguage : ''),
          (isset($response_data->NumberOfSuffix) ? $response_data->NumberOfSuffix : ''),
          (isset($response_data->ServicePointDetail->GeographicalLocationInfo->GeographicalLocation->Longitude->Value) ? $response_data->ServicePointDetail->GeographicalLocationInfo->GeographicalLocation->Longitude->Value : ''),
          (isset($response_data->ServicePointDetail->GeographicalLocationInfo->GeographicalLocation->Latitude->Value) ? $response_data->ServicePointDetail->GeographicalLocationInfo->GeographicalLocation->Latitude->Value : ''),
          (!empty($warnings) ? implode('; ', $warnings) : '')
        ));
        fputcsv($fp, $data);
      }
    }
  } catch (ClientException $e) {
    $request_error = Psr7\str($e->getResponse());
  } catch (ServerException $e) {
    $request_error = Psr7\str($e->getResponse());
  } catch (Exception $e) {
    $request_error = $e->getMessage();
  }

  fclose($fp_error);
  fclose($fp);

  if (isset($request_error) && $request_error !== FALSE) {
    file_put_contents($directory.'/request.error', json_encode($request, JSON_PRETTY_PRINT));

    trigger_error($request_error, E_USER_ERROR);
  }

  $source = array();
}

exit();
