<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/vendor/autoload.php';

$Bezug = 0;
$Betrag = 0;

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

        $datestart = strtotime("-0 month");
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

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
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

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function GeneratePDF($author, $filename, $Startdatum, $Enddatum, $MieterID)
    {
        
        $logo = $this->ReadPropertyString('LogoData');
        //echo($logo);
        if (strpos(base64_decode($logo), '<svg') !== false) {
            $logo = base64_decode($logo);
            $pdf->ImageSVG('@' . $logo, $x = 150, $y = 0, $w = 50, $h = 50, $border = 1);
            $logo = '';
        } elseif ($logo != '') {
            $logo = '<img src="@' . $logo . '" style="width: 200px">';
        }

    
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

        $pdf->SetFont('DejaVu Sans', 10);

        // add page 1
        $pdf->AddPage('P', 'A4');
        $pdf->setPage(1, true);
       
        $pdf->SetY(5);

        $pdf->setFontSize(10);

        $pdf->writeHTML($this->GenerateHTMLHeaderSeite1($logo), true, false, true, false, '');
        $pdf->setY($pdf->getY() + 10);
        $this->BerechneBezugUndBetrag($Startdatum, $Enddatum, $MieterID);
        $pdf->setFontSize(10);
        $pdf->writeHTML($this->GenerateHTMLTextSeite1($Startdatum, $Enddatum, $MieterID));

        // add page 2
        $pdf->AddPage('P', 'A4');
        $pdf->setPage(2, true);
        $pdf->SetY(5);

        $pdf->setFontSize(10);
        $pdf->writeHTML($this->GenerateHTMLHeaderSeite2($logo, $MieterID), true, false, true, false, '');
        $pdf->setY($pdf->getY() + 10);
        $pdf->setFontSize(10);
        $pdf->writeHTML($this->GenerateHTMLTextSeite2($Startdatum, $Enddatum, $MieterID));

        //Save the pdf
        return $pdf->Output($filename, 'S');
    }


        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        private function getDeutscherMonat(int $monatsNummer, bool $kurz = false) {
        $monate = [
            1 => ['Januar', 'Jan'],
            2 => ['Februar', 'Feb'],
            3 => ['März', 'Mär'],
            4 => ['April', 'Apr'],
            5 => ['Mai', 'Mai'],
            6 => ['Juni', 'Jun'],
            7 => ['Juli', 'Jul'],
            8 => ['August', 'Aug'],
            9 => ['September', 'Sep'],
            10 => ['Oktober', 'Okt'],
            11 => ['November', 'Nov'],
            12 => ['Dezember', 'Dez']
        ];

        // Überprüfen, ob die Nummer gültig ist
        if (!isset($monate[$monatsNummer])) {
            return "Ungültiger Monat";
        }

        // Rückgabe: Index 0 für voll, Index 1 für kurz
        return $kurz ? $monate[$monatsNummer][1] : $monate[$monatsNummer][0];
    }

    
   	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function removeKomma($str) {
		return str_replace(",", "", $str);
	}

/* 
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function GenerateHTMLHeader(string $logo, $Startdatum, $Enddatum, $MieterID)
    {

        $start = date_create($Startdatum);
        $start = date_modify($start, '-1 month');
        $mon = intval($start->format('m'));
        
        $monstr = $this->getDeutscherMonat($mon);


        $date = $monstr.' '.$start->format('Y');
        
        $Mietername = IPS_GetProperty($MieterID, "Mietername");
        $title =  $Mietername;

        return <<<EOT
        <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
            <td>
                <br/><br/><br/>
                Abrechnungsperiode $date<br/>
                <h1 style="font-weight: normal; font-size: 15pt">$title </h1>
            </td>
            <td width="50%" align="right"><br>$logo</td>
        </tr>
        </table>
        EOT;
    }
 */


    
////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////
    function GenerateHTMLHeaderSeite1($logo)
    {
		return '
		<style>
	    	*{font-size: 10pt;}
		
            body {
            font-family: "DejaVu Sans", "DejaVu Sans", sans-serif;
            font-size: 10pt;
        }
        </style>
	
		<div>
			'.$logo.'
		</div>
		<br/>
	    <br/>
		<br/>';
    }



////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////
	function GenerateHTMLHeaderSeite2($logo, $MieterID)
	{
		

		$Mietername = IPS_GetProperty($MieterID, "Mietername");

		$actdate = date('d.m.Y'); 


		$text = '
			<div  style="width: 100%">
				<style>
				  table {
					width: 100%;
						border-collapse: collapse; /* Verhindert doppelte Linien */
					}
					th, td {
						vertical-align: top; /* Zwingt den Inhalt nach oben */
						text-align: left;
					}
				</style>
				<table>
					<tr>
						<td width="55%" style="text-align: left; font-weight: bold;" >
							<div>
								'.$logo.'
							</div>
						</td>
						<td width="45%" style="text-align: left; font-weight: bold;" >
						
							<table width="100%">
								<tr>
									<td width="50%" style="text-align: left; font-weight: bold;">
										Kunde
									</td>
									<td width="50%" style="text-align: left; font-weight: normal;">
										'.$Mietername.'
									</td>
								</tr>
								<tr>
									<td width="50%" style="text-align: left; font-weight: bold;">
										Rechnungsdatum
									</td>
									<td width="50%" style="text-align: left; font-weight: normal;">
										'.$actdate.'
									</td>
								</tr>
						  </table>
						</td>
					</tr>
			  </table>
				<br/>
				<br/>
		</div>
		
		<br/>';
		
		return $text;
	}



////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////
	function GenerateHTMLTextSeite1($Startdatum, $Enddatum, $MieterID)
	{

		$Objektname = IPS_GetProperty($MieterID, "Objektname");

		global $Bezug; // berechnet auf Seite 2
		global $Betrag; // berechnet auf Seite 2
		
		$MwSt = 8.1;
		$MwstBetrag = ($Betrag * $MwSt) / 100;
		$BetragInclMwst = $Betrag + $MwstBetrag;
		$gerundet = round($BetragInclMwst * 20) / 20; 
		$RundungsDifferenz = -($BetragInclMwst - $gerundet);
		$BetragInclMwstGerundet = $gerundet;

		$ersterTag = date('01.m.Y', strtotime($Startdatum));
		$letzterTag = date('t.m.Y', strtotime($Enddatum));
		
        //$start = date_modify($start, '-1 month');
		
        //$mon = intval($start->format('m'));
        
        //$monstr = getDeutscherMonat($mon);

		
        $actdate = date('d.m.Y'); 

		
		$MieterAdresse = IPS_GetProperty($MieterID, "Adresse");
		
		$MieterAdresse = str_replace("\n", "<br>", $MieterAdresse);

		$Mobimoadresse = "Mobimo AG\nSeestrasse 59\n8700 Küsnacht ZH\ninfo@mobimo.ch\nTel +41 44 397 11 11";

		$Mobimoadresse = str_replace("\n", "<br>", $Mobimoadresse);


		// Addressblöcke nebeneinander
		
		$text = '
		<style>
		  .linie-dünn-ganze-breite {
			border: 0;
			height: 1px;
			background-color: black; 
			width: 100%;
			margin: 10px 0; /*Abstand oben/unten */
		  }
		</style>

		<div  style="width: 100%">
			<style>
			  table {
				width: 100%;
					border-collapse: collapse; /* Verhindert doppelte Linien */
				}
				th, td {
					vertical-align: top; /* Zwingt den Inhalt nach oben */
					text-align: left;
				}
			</style>
			<table>
				<tr>
					<td width="60%" style="text-align: left; font-weight: bold;" >
						Abrechnungssteller
					</td>
					<td width="40%" style="text-align: left; font-weight: bold;" >
					</td>
				</tr>
				<tr>
					<td width="60%" style="text-align: left;vertical-align: top;" >
						 <div style="
							vertical-align: top;
							display: inline-block; /* Passt sich an Inhalt an */
							min-width: 100px;     /* Optional: Mindestbreite */
							background: transparent; /* Kein Hintergrund */
							word-wrap: break-word; /* Umbruch bei langem Text */
							padding: 0;
							margin: 0;">'.$Mobimoadresse.'
						</div>
					</td>
					<td width="40%" style="text-align: left;vertical-align: top;">
						 <div style="
							vertical-align: top;
							display: inline-block; /* Passt sich an Inhalt an */
							min-width: 100px;     /* Optional: Mindestbreite */
							background: transparent; /* Kein Hintergrund */
							word-wrap: break-word; /* Umbruch bei langem Text */
							padding: 0;
							margin: 0;">'.$MieterAdresse.'
						</div>
					</td>
				</tr>
		  </table>
			<br/>
			<br/>
		</div>
		<div  style="width: 45%">
		<table width="100%">
			<tr>
				<td width="50%" style="text-align: left; font-weight: bold;font-size: 10pt;">
					Rechnungsdatum
				</td>
				<td width="50%" style="text-align: left;font-size: 10pt;">
					'.$actdate.'
				</td>
			</tr>
			<tr>
				<td width="50%" style="text-align: left; font-weight: bold;font-size: 10pt;">
					Abrechnungsperiode
				</td>
				<td width="50%" style="text-align: left;font-size: 10pt;">
					'.$ersterTag.' bis '.$letzterTag.'
				</td>
			</tr>
      </table>
	  </div>
	  <br/>
	  <br/>';
		
		// Titel Stromabrechnung
		
		$text .= '
			<div  style="font-weight: bold; font-size: 12pt; padding: 4px;">
				Stromabrechnung '. $Objektname .'
			</div>
		
		';

		$text .= '<hr class="linie-dünn-ganze-breite">';

		//  Tabelle Abrechnung Zusammenfassung

		$text .= '
		<div  style="width: 100%">
		<table width="100%">
			<tr>
				<td width="50%" style="text-align: left;padding: 4px;font-size: 10pt;">
					Betrag excl. MwSt.
				</td>
				<td width="50%" style="text-align: right;padding: 4px;font-size: 10pt;">
					'.$this->removeKomma(number_format($Betrag, 2)).'
				</td>
			</tr>
			<tr>
				<td width="50%" style="text-align: left;padding: 4px;font-size: 10pt;">
					MwSt. 8.1%
				</td>
				<td width="50%" style="text-align: right;padding: 4px;font-size: 10pt;">
					'.$this->removeKomma(number_format($MwstBetrag, 2)).'
				</td>
			</tr>
			<tr>
				<td width="50%" style="text-align: left;padding: 4px;font-size: 10pt;">
					Betrag incl. MwSt.
				</td>
				<td width="50%" style="text-align: right;padding: 4px;font-size: 10pt;">
					'.$this->removeKomma(number_format($BetragInclMwst, 2)).'
				</td>
			</tr>';
			
			if ($RundungsDifferenz != 0) {
				$text .= '
			<tr>
				<td width="50%" style="text-align: left;padding: 4px;font-size: 10pt;">
					Rundungsdifferenz
				</td>
				<td width="50%" style="text-align: right;padding: 4px;font-size: 10pt;">
					'.$this->removeKomma(number_format($RundungsDifferenz, 2)).'
				</td>
			</tr>';
			}
			
		$text .= '
		</table>
		</div>';

		$text .= '<hr class="linie-dünn-ganze-breite">';

		
		//  Tabelle Abrechnung Total Zeile
		
		$text .= '
		<div  style="width: 100%">
		<table width="100%">
			<tr>
				<td width="50%" style="text-align: left;font-weight: bold; font-size: 10pt;">
					Total CHF
				</td>
				<td width="50%" style="text-align: right;font-weight: bold; font-size: 10pt;">
					'.$this->removeKomma(number_format($BetragInclMwstGerundet, 2)).'
				</td>
			</tr>
		</table>
		</div>
		<br/>
		<br/>';
		
		return $text;
	}


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function BerechneBezugUndBetrag($Startdatum, $Enddatum, $MieterID)
    {
        $data = $this->FetchData($Startdatum, $Enddatum, $MieterID);
        if ($data == []) {
            echo 'xxxxxxxxxxxxx empty data xxxxxxxxxxxxxx';
            return;
        }
        global $Bezug; // berechne hier für Seite 1
        global $Betrag; // berechne hier für Seite 1


		$Rabatt = IPS_GetProperty($MieterID, "Rabatt");
		 
		$tariff = $this->ReadPropertyFloat("Tariff");
		
        $Bezug = 0;
        $text = '';
    	foreach ($data as $key  => $variable) 
		{
			$consumption = strval($variable['kWh']);
			$percentage = strval($variable['AnteilProzent']);
			$consumptioncalc = strval(($variable['kWh'] * $percentage) / 100);
			$Bezug += $consumptioncalc;
		}

		$Bezug = round($Bezug, 2);  // gerundeter Bezug

		$TotalCHFOhneRabatt = round($Bezug * $tariff, 2);

		$Betrag = ($TotalCHFOhneRabatt * (100-$Rabatt)) / 100;

		$Betrag = round($Betrag, 2);  // gerundeter Betrag

    }

    
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function GenerateHTMLTextSeite2($Startdatum, $Enddatum, $MieterID)
    {
        $data = $this->FetchData($Startdatum, $Enddatum, $MieterID);
        if ($data == []) {
            echo 'xxxxxxxxxxxxx empty data xxxxxxxxxxxxxx';
            return;
        }
        global $Bezug; // berechne hier für Seite 1
        global $Betrag; // berechne hier für Seite 1


		$Rabatt = IPS_GetProperty($MieterID, "Rabatt");
		 
		$tariff = $this->ReadPropertyFloat("Tariff");
		
        $text = '
 		<div  style="font-weight: bold; font-size: 12pt; padding: 4px;">
			Übersicht
		</div>
		<br/>

		<table border-collapse: collapse; width="100%">
		<thead>
		<tr>
		  <th style="width: 25%; text-align: left; padding: 4px; font-size: 10pt;">
			Bezug
		  </th>
		  <th style="width: 25%; text-align: right; padding: 4px; font-size: 10pt;">
			Tarif
		  </th>
		  <th style="width: 25%; text-align: right; padding: 4px; font-size: 10pt;">
			Rabatt
		  </th>
		  <th style="width: 25%; text-align: right; padding: 4px; font-size: 10pt;">
			Total
		  </th>
		</tr>
	    </thead>';

		$text .= '<tr>
			<td style="text-align: left; padding: 4px;border-bottom: 1px solid black; font-size: 10pt;">kWh</td>
			<td style="text-align: right; padding: 4px;border-bottom: 1px solid black; font-size: 10pt;">CHF/kWh</td>
			<td style="text-align: right; padding: 4px;border-bottom: 1px solid black; font-size: 10pt;">%</td>
			<td style="text-align: right; padding: 4px;border-bottom: 1px solid black;font-size: 10pt; ">CHF</td>
		</tr>';


		$text .= '<tr>
			<td style="text-align: left; padding: 4px; font-size: 10pt;">'.$this->removeKomma(strval($Bezug)).'</td>
			<td style="text-align: right; padding: 4px; font-size: 10pt;">'.strval($tariff).'</td>
			<td style="text-align: right; padding: 4px; font-size: 10pt;">'.$this->removeKomma(number_format($Rabatt, 2)).'</td>
			<td style="text-align: right; padding: 4px; font-size: 10pt;">'.$this->removeKomma(number_format($Betrag, 2)).'</td>
		</tr>
         </table>';
	   
		
		$text .= '
		<style>
		  .linie-dick-ganze-breite {
			border: 0;
			height: 2px; /* Höhe der Linie */
			background-color: #333; /* Farbe der Linie */
			width: 100%; /* Breite */
			margin: 20px 0; /* Abstand oben/unten */
		  }
		</style>


        <br/>
		<br/>
		<br/>
		<div  style="font-weight: bold; font-size: 12pt; padding: 4px;">
			Details
		</div>
		<br/>
        
	
		<table border-collapse: collapse; width="100%">
		<thead>
		<tr>
		  <!-- Linksbündig, 55% Breite -->
		  <th style="width: 55%; text-align: left; padding: 4px;font-size: 10pt;">
			Zähler
		  </th>
		  <!-- Rechtsbündig, 15% Breite -->
		  <th style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">
			Bezug
		  </th>
		  <!-- Rechtsbündig, 15% Breite -->
		  <th style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">
			Anteil
		  </th>
		  <th style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">
			Total
		  </th>
		</tr>
	  </thead>';
		

		
		$text .= '
		
		<tr border-bottom: 1px solid black;>
		<td style="width: 55%; text-align: left; border-bottom: 1px solid black; padding: 4px;font-size: 10pt;"></td>
		<td style="width: 15%; text-align: right; border-bottom: 1px solid black; padding: 4px;font-size: 10pt;">kWh</td>
		<td style="width: 15%; text-align: right; border-bottom: 1px solid black; padding: 4px;font-size: 10pt;">%</td>
		<td style="width: 15%; text-align: right; border-bottom: 1px solid black; padding: 4px;font-size: 10pt;">kWh</td>
	    </tr>';

		foreach ($data as $key  => $variable) 
		{
			$name = $variable['Zählername'];
			$consumption = $variable['kWh'];
			$percentage = $variable['AnteilProzent'];
			$consumptioncalc = ($variable['kWh'] * $percentage) / 100;
 
			$text .= '<tr>
					<td style="width: 55%; text-align: left; padding: 4px;font-size: 10pt;">'.$name.'</td>
					<td style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">'.$this->removeKomma(number_format($consumption,2)).'</td>
					<td style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">'.$this->removeKomma(number_format($percentage,2)).'</td>
					<td style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">'.$this->removeKomma(number_format($consumptioncalc,2)).'</td>
				</tr>';
				
		}

		$text .= '</table>';
		
		$text .= '<hr class="linie-dick-ganze-breite">';

		$text .= '
            <br/>
            <table border-collapse: collapse; width="100%">
                <tr>
 					<td style="width: 55%; text-align: left; padding: 4px;font-size: 10pt;"></td>
					<td style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;"></td>
					<td style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;"></td>
					<td style="width: 15%; text-align: right; padding: 4px;font-size: 10pt;">'.$this->removeKomma(number_format($Bezug,2)).'</td>
               
                </tr>
            </table>';

        return $text;
    }



    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function FetchData($Startdatum, $Enddatum, $MieterID)
    {
        // alle zähler für diesen mieter
        
        $data = [];
                
        $zählerliste = json_decode(IPS_GetProperty($MieterID, "Zählerliste"));
        //print_r ($zählerliste[0]).PHP_EOL;
        foreach($zählerliste as $zähler) {
            //echo 'Zähler: '.$zähler->Zähler.PHP_EOL;
            //echo 'Prozent: '.$zähler->AnteilProzent.PHP_EOL;

            $IPadr = IPS_GetProperty($zähler->Zähler, 'IPAdresse');
            //echo 'IP: '.$IPadr.PHP_EOL;

            $Name = IPS_GetName($zähler->Zähler);

            $sum = $this->ReadZähler($IPadr, $Startdatum, $Enddatum);
            $sum = ($sum * $zähler->AnteilProzent) / 100;
            array_push($data, ['Zählername' =>  $Name, 'kWh' => $sum, 'AnteilProzent' => $zähler->AnteilProzent] );

        }
        return $data;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function ReadZähler($IPAdr, $Startdatum, $Enddatum)
    {

        $response = file_get_contents('http://'.$IPAdr.'/data.json?type=MONTHLYPROFILE');
        $json = json_decode($response, true);
        $sum = 0;

        // 'aaaaa '.$Startdatum.' aaaaa '.PHP_EOL;
        //echo 'bbbbb '.$Enddatum.' bbbbb '.PHP_EOL;

        $start = strtotime($Startdatum);
        $end = strtotime($Enddatum);

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
        //echo 'xxxxx '.$sum.' xxxx '.PHP_EOL;
        return $sum;
    }
}
