<?php

class BillingEngine extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyFloat("Tariff", 0.25);

        $this->RegisterVariableInteger("InvoiceCounter", "Rechnungszähler");
        $this->RegisterVariableString("LastInvoiceNumber", "Letzte Rechnungsnummer");
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            "elements" => [
                [
                    "type" => "NumberSpinner",
                    "name" => "Tariff",
                    "caption" => "Tarif (Preis pro kWh)",
                    "digits" => 4
                ]
            ],
            "actions" => [
                [
                    "type" => "Button",
                    "caption" => "Alle Mieter abrechnen",
                    "onClick" => "BILL_RunBilling($id);"
                ]
            ]
        ]);
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

    private function GenerateInvoiceNumber(int $tenantID): string
    {
        $counter = GetValue($this->GetIDForIdent("InvoiceCounter")) + 1;
        SetValue($this->GetIDForIdent("InvoiceCounter"), $counter);

        $number = date("Y-m") . "-$tenantID-" . str_pad($counter, 4, "0", STR_PAD_LEFT);
        SetValue($this->GetIDForIdent("LastInvoiceNumber"), $number);

        return $number;
    }
}
