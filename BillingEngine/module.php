<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/vendor/autoload.php';

class BillingEngine extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyFloat("Tariff", 0.250);
        //$this->RegisterPropertyString("StartDatum", "01.01.2026");
        //$this->RegisterPropertyString("EndDatum", "01.01.2026");
        $this->RegisterPropertyString('LogoData', '');
        $this->RegisterVariableInteger("PdfIdx", 'PdfIdx');
        $this->SetValue('PdfIdx', 1); 
        
        //$this->RegisterVariableInteger("InvoiceCounter", "Rechnungszähler");
        //$this->RegisterVariableString("LastInvoiceNumber", "Letzte Rechnungsnummer");

        //$mediaID = $this->RegisterMediaDocument('ReportPDF', $this->Translate('Report (PDF)'), 'pdf');

    }
 

    private function RegisterMediaDocument($Ident, $Name, $Extension, $Position = 0)
    {
        $this->RegisterMedia(5, $Ident, $Name, $Extension, $Position);
    }

    private function RegisterMedia($Type, $Ident, $Name, $Extension, $Position)
    {
        $mediaId = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if (true) { //$mediaId === false) {
            //echo 'RegisterMedia ident not found, create'.PHP_EOL;
        //    echo 'RegisterMedia new media Ident='.$Ident.PHP_EOL;
            $mediaId = IPS_CreateMedia(5 /* Document */);
            IPS_SetParent($mediaId, $this->InstanceID);
            IPS_SetIdent($mediaId, $Ident);
            IPS_SetName($mediaId, $Name);
            IPS_SetPosition($mediaId, $Position);
            IPS_SetMediaFile($mediaId, 'media/' . $mediaId . '.pdf', false);
        } else {
            echo 'RegisterMedia ident found, no create'.PHP_EOL;
        }
    }
 

    public function EinenMieterAbrechnen(int $MieterID, string $Startdatum, string $Enddatum)
    {
        
        $Mietername = IPS_GetProperty($MieterID, "Mietername");

        //echo $Mietername.'_'.$Startdatum.'_'.$Enddatum.PHP_EOL;

        $filename = $Mietername.'_'.$Startdatum.'_'.$Enddatum.'.pdf';
        $filepath = 'media/'.$filename;

        error_reporting(0);
        // delete old media with same name
        try {
            $mediaID = @IPS_GetObjectIDByName($filename, $this->InstanceID);
            IPS_DeleteMedia($mediaID, true);
        }
        catch(Exception $e) {
            
        }
        error_reporting(E_ERROR | E_WARNING);

        $pdfidx = GetValue($this->GetIDForIdent("PdfIdx"));
        $pdfidx++;
        $this->SetValue('PdfIdx', $pdfidx) ;
        
        $newIdent = 'ReportPDF'.$pdfidx;
        //echo 'new media Ident='.$newIdent.PHP_EOL;

        $mediaID = $this->RegisterMediaDocument($newIdent, $filename, 'pdf');
        //echo 'new media ID='.$mediaID.PHP_EOL;

       // $mediaID = @IPS_GetObjectIDByIdent('ReportPDF', $this->InstanceID);
         $mediaID = @IPS_GetObjectIDByName($filename, $this->InstanceID);

        $filename = $Mietername.'_'.$Startdatum.'_'.$Enddatum.'.pdf';
        $filepath = 'media/'.$filename;
        IPS_SetMediaFile($mediaID, $filepath, false);

        $pdfContent = $this->GeneratePDF('Amrein-Projekt ' . IPS_GetKernelVersion(), 'report.pdf', $Startdatum, $Enddatum, $MieterID);

        if ($this->GetStatus() >= IS_EBASE) {
            return false;
        }

        $mediaID = $this->GetIDForIdent($newIdent);
        IPS_SetMediaContent($mediaID, base64_encode($pdfContent));

        
        return true;
    }


    public function AlleMieterLetztenMonatAbrechnen()
    {

        $datestart = strtotime("-1 month");
        $datestart = strtotime(date('Y-m-01', $datestart));
        $dateend = strtotime(date('Y-m-t', $datestart));

        //echo date('Y-m-d', $datestart);
        //echo date('Y-m-d', $dateend);

        $instlist = IPS_GetInstanceList();
        foreach ($instlist as $inst) {
            $instarr = IPS_GetInstance($inst);
            $instID = $instarr['InstanceID'];

            $modinfoName = $instarr['ModuleInfo']['ModuleName'];
          
            if ($modinfoName == 'Tenant')
            {
                //echo $modinfoName.PHP_EOL;
                //echo IPS_GetName($instID).PHP_EOL;
                BILL_EinenMieterAbrechnen($this->InstanceID, $instID , date('Y-m-d', $datestart), date('Y-m-d', $dateend) );
            }
        }


        return true;
    }

    public function RunBilling()
    {
        $tenants = IPS_GetInstanceListByModuleID("{8EF9FC78-699F-9FDF-4DE5-BECD724F5CE9}");
        $meters  = IPS_GetInstanceListByModuleID("{416F5F49-DAE6-45B7-3626-E5B339F3012D}");

        foreach ($tenants as $tenantID) {

            $details = [];
            $totalKWh = 0;

            foreach ($meters as $meterID) {

                if (IPS_GetProperty($meterID, "TenantID") !== $tenantID) {
                    continue;
                }

                $kwh   = GetValue(IPS_GetObjectIDByIdent("PeriodConsumptionkWh", $meterID));
                $share = IPS_GetProperty($meterID, "SharePercent");

                $billed = round($kwh * ($share / 100), 3);
                $totalKWh += $billed;

                $details[] = [
                    "Meter"     => IPS_GetName($meterID),
                    "KWh"       => $kwh,
                    "Share"     => $share,
                    "BilledKWh" => $billed
                ];
            }

            if (count($details) === 0) {
                continue;
            }

            $invoice = $this->GenerateInvoiceNumber($tenantID);
            $net = round($totalKWh * $this->ReadPropertyFloat("Tariff"), 2);

            $exportID = IPS_GetInstanceListByModuleID("{C9D8F3B0-4000-0000-0000-EXPORT0001}")[0];
            BILLINGEXPORT_CreateInvoice($exportID, $invoice, $tenantID, $details, $net);
        }
    }

    private function GeneratePDF($author, $filename, $Startdatum, $Enddatum, $MieterID)
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($author);
        $pdf->SetTitle('');
        $pdf->SetSubject('');

        $pdf->setPrintHeader(false);

        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP - 5, PDF_MARGIN_RIGHT);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->setPrintFooter(false);

        $pdf->SetFont('dejavusans');

        $pdf->AddPage();

        //PDF Content
        //Header
        $logo = $this->ReadPropertyString('LogoData');
        //echo($logo);
        if (strpos(base64_decode($logo), '<svg') !== false) {
            $logo = base64_decode($logo);
            $pdf->ImageSVG('@' . $logo, $x = 150, $y = 0, $w = 50, $h = 50, $border = 1);
            $logo = '';
        } elseif ($logo != '') {
            $logo = '<img src="@' . $logo . '">';
        }

        $pdf->writeHTML($this->GenerateHTMLHeader($logo, $Startdatum, $Enddatum, $MieterID), true, false, true, false, '');

        //Charts
        //if (IPS_VariableExists($this->ReadPropertyInteger('TemperatureID'))) {
        //    $svg = $this->GenerateCharts($this->ReadPropertyInteger('TemperatureID'));
        //    $pdf->ImageSVG('@' . $svg, $x = 105, $y = '', $w = 90, $h = '', $link = '', $align = 5, $palign = 5, $border = 0, $fitonpage = true);
        //}
        //$svg = $this->GenerateCharts($this->ReadPropertyInteger('CounterID'));
        //$pdf->ImageSVG('@' . $svg, $x = 10, $pdf->GetY(), $w = 90, $h = '', $link = '', $align = '', $palign = '', $border = 0, $fitonpage = true);

        //reset Y
        $pdf->setY($pdf->getY() + 62);

        //text
        $pdf->writeHTML($this->GenerateHTMLText($Startdatum, $Enddatum, $MieterID), true, false, true, false, '');

        //Save the pdf
        return $pdf->Output($filename, 'S');
    }

    private function GenerateHTMLHeader(string $logo, $Startdatum, $Enddatum, $MieterID)
    {
        $date = strtoupper(date('dd.mm.YYYY', strtotime($Startdatum))) . ' bis ' . date('dd.mm.YYYY', strtotime($Enddatum));
        
        $Mietername = IPS_GetProperty($MieterID, "Mietername");
        $title =  $Mietername;

        return <<<EOT
        <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
            <td>
                <br/><br/><br/>
                $date<br/>
                <h1 style="font-weight: normal; font-size: 10px">$title </h1>
            </td>
            <td width="50%" align="right"><br>$logo</td>
        </tr>
        </table>
        EOT;
    }


    private function GenerateHTMLText($Startdatum, $Enddatum, $MieterID)
    {
        $data = $this->FetchData($Startdatum, $Enddatum, $MieterID);
        if ($data == []) {
            return;
        }

        // $title = strtoupper($this->Translate('Behave'));
        // $consumption = sprintf($this->Translate('In %s you used up %s.'), $data['month'], $data['consumption']) . ' ';
        // if ($data['consumptionLastYear'] !== false) {
        //     $consumption .= $data['consumptionLastYear'];
        // }

        // if ($data['prediction'] != '') {
        //     $predictionText = $this->Translate('Expected usage based on your behavior') . ': ' . $data['prediction'] . ' <br> ';
        // } else {
        //     $predictionText = '';
        // }

        // $valueText = $this->Translate('Actual usage') . ': ' . $data['consumption'] . ' <br><br> ';

        // if ($data['percent']) {
        //     $consumptionText = $this->Translate("You could $data[percentText] your consumption by %s in the period");
        //     $consumptionText2 = sprintf($consumptionText, $data['percent']);
        // } else {
        //     $consumptionText = '';
        //     $consumptionText2 = '';
        // }
        $text = '';

        $text +=
        <<<EOT
        <br> </br>
            <p>
            $data[0]['Zähler'] <br>
            </p>
            <p>
            $data[0]['kWh'] <br>
            </p>
        EOT;

        // $comparison = $this->Translate('For Comparison');
        // $co2text = $this->Translate('A tree bind each month ca. 1 kg CO².<br>In order to achieve the 2030 climate targets, the total energy consumption for heating and hot water must be reduced by <br>5.5% per year.<br>Your personal footprint can be found <a href = "uba.co2-rechner.de" >here </a> calculate.');
 
        // if ($data['co2'] > 0) {
        //     $text .=
        //     <<<EOT
        //     <h3>CO² Emmisionen</h3> 
        //     <table cellpadding="0" cellspacing="0" border="0" width="100%">
        //         <tr>
        //             <td width="20%"><p>    $data[co2] kg</p></td>
        //             <td width="80%"><h4>$comparison</h4><p>$co2text</p></td>
        //         </tr>
        //     </table>
        //     EOT;
        // }
        return $text;
    }

    private function FetchData($Startdatum, $Enddatum, $MieterID)
    {
        // alle zähler für diesen mieter
        
        $data = [];
                
        $zählerliste = json_decode(IPS_GetProperty($MieterID, "Zählerliste"));
        print_r ($zählerliste[0]).PHP_EOL;
        foreach($zählerliste as $zähler) {
            //echo 'Zähler: '.$zähler->Zähler.PHP_EOL;
            //echo 'Prozent: '.$zähler->AnteilProzent.PHP_EOL;

            $IPadr = IPS_GetProperty($zähler->Zähler, 'IPAdresse');
            //echo 'IP: '.$IPadr.PHP_EOL;

            $Name = IPS_GetName($zähler->Zähler);

            $sum = $this->ReadZähler($IPadr, $Startdatum, $Enddatum);
            $sum = ($sum * 100) / $zähler->AnteilProzent;

            array_push($data, ['Zählername' =>  $Name, 'kWh' => $sum] );

        }
        return $data;
    }

    private function ReadZähler($IPAdr, $Startdatum, $Enddatum)
    {

        $response = file_get_contents('http://'.$ipadress.'/data.json?type=MONTHLYPROFILE');
        $json = json_decode($response, true);
        $sum = 0;
        $start = strtotime($startstr);
        $end = strtotime($endstr);

        $dat = strtotime($json['MONTHLYPROFILE']['INST']['TS']);
        //echo 'xxx'.$dat.PHP_EOL;
        if ($dat >= $start && $dat < $end) {
        //echo $json['MONTHLYPROFILE']['INST']['TS'].PHP_EOL;
        //echo $json['MONTHLYPROFILE']['INST']['import'].PHP_EOL;
        $sum += $json['MONTHLYPROFILE']['INST']['import'];
        }
        for ($i = 0; $i<1000; $i++) {

        if (isset($json['MONTHLYPROFILE']['data'][strval($i)])) 
        {
            $dat = strtotime($json['MONTHLYPROFILE']['data'][strval($i)]['TS']);
            if ($dat >= $start && $dat < $end) {
            //echo 'xxx'.$dat.PHP_EOL;
            //echo $json['MONTHLYPROFILE']['data'][strval($i)]['TS'].PHP_EOL;
            //echo $json['MONTHLYPROFILE']['data'][strval($i)]['import'].PHP_EOL;
            $sum += $json['MONTHLYPROFILE']['data'][strval($i)]['import'];
            }
        } else {
            //break;
        }
        }
        return $sum;
    }
}
