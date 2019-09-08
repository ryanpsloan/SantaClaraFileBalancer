<?php

/***********************************************************************************************************************
 * Author: Ryan Pace Sloan
 *
 *
 *
 *
 *
 ***********************************************************************************************************************/
include 'FileUploader.php';
session_start();
if(isset($_FILES)) {
    try{
        $directory = '/var/www/html/SantaClara/SCFiles';
        $newFileName = 'SantaClara_GL';
        $fu = new FileUploader($_FILES['file'], $directory, $newFileName);
        //var_dump($fu);

        $fileData = array();
        $handle = fopen($fu->getNewFileName(), "r");
        $headers = explode(",", trim(fgets($handle)));
        //var_dump($headers);
        while(!feof($handle)){
           $fileData[] = explode(",", trim(fgets($handle)));
        }
        fclose($handle);
        //var_dump('FILEDATA', $fileData);

        $data = array();
        $totalDebitSum = $totalCreditSum = 0.00;
        foreach($fileData as $key => $value){
            if(is_array($value) && count($value) === 12) {
                $transactionDesc = $value[2];
                $earningCode = $value[11];
                $data[$transactionDesc][$earningCode][] = array('document#' => $value[0], 'effectiveDate' => $value[1], 'transactionDescription' => $transactionDesc, 'fund' => $value[3], 'fiscalYear' => $value[4], 'department' => $value[5], 'project' => $value[6], 'glCode' => $value[7], 'debit' => (float) $value[8], 'credit' => (float) $value[9], 'hours' => $value[10], 'earningCode' => $earningCode);
                $totalDebitSum += $value[8];
                $totalCreditSum += $value[9];
            }
        }
        $totalSumArray = array('debitTotalSum' => $totalDebitSum, 'creditTotalSum' => $totalCreditSum);
        //var_dump('DATA', $totalDebitSum, $totalCreditSum, $data);

        $balance = array();
        foreach($data as $transactionDesc => $array){
            //var_dump($transactionDesc, $array);
            foreach($array as $earningCode => $arr){
                //var_dump($earningCode, $arr);
                $debits = $credits = array();
                $sumDebits = $sumCredits = 0.00;

                foreach($arr as $key => $value){
                    $debits[] = $value['debit'];
                    $credits[] = $value['credit'];

                }

                $sumDebits = array_sum($debits);
                $sumCredits = array_sum($credits);
                $balance[$transactionDesc][$earningCode][] = array($sumDebits, $sumCredits);
                //var_dump('DEBITS', $sumDebits, $debits, 'CREDITS', $sumCredits, $credits);
            }
        }
        //var_dump('BALANCE', $balance);

        $output = $toBalance = array();
        $earningCodesToBalance = array('WC Impound');
        foreach($balance as $transactionDesc => $array) {

            foreach ($array as $earningCode => $arr) {

                foreach ($arr as $key => $value) {
                    if(in_array($earningCode, $earningCodesToBalance)){
                        $dbt = $cdt = '';
                        $debit = round($value[0], 2);
                        $credit = round($value[1], 2);
                        if($debit === $credit){
                            $code = "$transactionDesc | $earningCode | Debit Total: $$debit | Credit Total: $$credit | <img src='img/checkmark-30x30.png' height='30' width='30'/>";
                            $output[$transactionDesc][$earningCode]['balance'] = $code;
                        }else {
                            if ($debit > $credit) {
                                $difference = number_format(($debit - $credit), 2);
                                $dbt = "<span class='highlight'>Debit Total = $$debit</span>";
                                $cdt = "Credit Total = $$credit";
                                $toBalance[$transactionDesc][$earningCode][] = array('transactionDesc' => $transactionDesc, 'debit' => $debit, 'credit' => $credit, 'difference' => $difference, 'debitTest' => true);
                            } else if ($debit < $credit) {
                                $difference = number_format(($credit - $debit), 2);
                                $dbt = "Debit Total = $$debit";
                                $cdt = "<span class='highlight'>Credit Total = $$credit</span>";
                                $toBalance[$transactionDesc][$earningCode][] = array('transactionDesc' => $transactionDesc, 'debit' => $debit, 'credit' => $credit, 'difference' => $difference, 'debitTest' => false);
                            }
                            $code = "$transactionDesc | $earningCode | $dbt | $cdt | <span class='red'>$$difference</span>";
                            $output[$transactionDesc][$earningCode]['notBalance'] = $code;

                        }
                    }
                }
            }
        }
        //var_dump('TOBALANCE', $toBalance);
        //var_dump('OUTPUT', $output);
        //var_dump(count($toBalance));

        $final = array();
        $linesCreated = 0;
        foreach($toBalance as $transactionDesc => $array){
            foreach($array as $earningCode => $arr){
                foreach($arr as $key => $value){
                    //var_dump($arr[$key]);
                    //var_dump($data[$transactionDesc][$earningCode][$key]);
                    $glCodeInput = '0000';
                    //credit
                    if ($arr[$key]['debitTest']) {
                        $newLine = array($data[$transactionDesc][$earningCode][$key]['document#'], $data[$transactionDesc][$earningCode][$key]['effectiveDate'],
                            $data[$transactionDesc][$earningCode][$key]['transactionDescription'], $data[$transactionDesc][$earningCode][$key]['fund'],
                            $data[$transactionDesc][$earningCode][$key]['fiscalYear'], $data[$transactionDesc][$earningCode][$key]['department'],
                            $data[$transactionDesc][$earningCode][$key]['project'], $glCodeInput,
                            '0.00', $diff = $toBalance[$transactionDesc][$earningCode][$key]['difference'],
                            $data[$transactionDesc][$earningCode][$key]['hours'], $data[$transactionDesc][$earningCode][$key]['earningCode']);
                        $output[$transactionDesc][$earningCode][] = "<span><strong>$newLine[2] | $newLine[11] &rarr; Added Credit Line: $" . $diff . " | GL Code: $glCodeInput</strong></span>";

                    }
                    //debit
                    else {
                        $newLine = array($data[$transactionDesc][$earningCode][$key]['document#'], $data[$transactionDesc][$earningCode][$key]['effectiveDate'],
                            $data[$transactionDesc][$earningCode][$key]['transactionDescription'], $data[$transactionDesc][$earningCode][$key]['fund'],
                            $data[$transactionDesc][$earningCode][$key]['fiscalYear'], $data[$transactionDesc][$earningCode][$key]['department'],
                            $data[$transactionDesc][$earningCode][$key]['project'], $glCodeInput,
                            $diff = $toBalance[$transactionDesc][$earningCode][$key]['difference'], '0.00',
                            $data[$transactionDesc][$earningCode][$key]['hours'], $data[$transactionDesc][$earningCode][$key]['earningCode']);
                        $output[$transactionDesc][$earningCode][] = "<span><strong>$newLine[2] | $newLine[11] &rarr; Added Debit Line: $" . $diff ." | GL Code: $glCodeInput</strong></span>";
                    }
                    $final[] = $newLine;
                    $linesCreated++;
                }
            }
        }

        //var_dump('FINAL BEFORE', $final);

        foreach($fileData as $data){
            if(is_array($data) && count($data) === 12){
                $final[] = $data;
            }
        }
        //var_dump(count($fileData), count($final), 'FINAL AFTER', $final);

        $emp = $gl = $pro = $comp = array();
        foreach ($final as $key => $row) {
            $emp[$key]  = $row[2];
            $gl[$key] = $row[5];
            $pro[$key] = $row[6];
            $comp[$key] = $row[7];
        }
        array_multisort($emp, SORT_ASC, $pro, SORT_ASC, $gl, SORT_ASC, $comp, SORT_ASC, $final);

        $finalDebitSum = $finalCreditSum = 0.00;
        foreach($final as $data){
            $finalDebitSum += $data[8];
            $finalCreditSum += $data[9];
        }
        $finalSum = array('finalDebitSum' => $finalDebitSum, 'finalCreditSum' => $finalCreditSum);

        //var_dump('SORTED FINAL', $final);

        $today = new DateTime('now');
        $dateFormat = $today->format("m-d-y-H-i-s");

        $fileName = "/var/www/html/SantaClara/processed/SantaClara_Processed_GL_File_" .$dateFormat . ".csv";
        $handle = fopen($fileName, 'wb');
        fputcsv($handle,$headers);
        for($i = 0; $i < count($final); $i++){
            fputcsv($handle, $final[$i]);
        }
        fclose($handle);

        $_SESSION['fileName'] = $fileName;
        $_SESSION['totalSum'] = $totalSumArray;
        $_SESSION['data'] = $output;
        $_SESSION['lineCount'] = count($final);
        $_SESSION['finalSum'] = $finalSum;
        $_SESSION['message'] = 'File Balanced Successfully. Ready for Download.';
        $_SESSION['linesCreated'] = $linesCreated;

        header("Location: ../index.php");

    }catch(Exception $e){
        $_SESSION['error'] = $e->getMessage();
        header("Location: ../index.php");
    }

}else{
    $_SESSION['error'] = 'File Not Selected.';
    header("Location: ../index.php");

}
?>