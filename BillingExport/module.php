<?php

class BillingExport extends IPSModule
{
    public function Create()
    {
        parent::Create();
    }

    public function CreateInvoice(string $invoice, int $tenantID, array $details, float $net)
    {
        $path = "/var/lib/symcon/invoices/" . date("Y") . "/Tenant_$tenantID/";
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        file_put_contents(
            $path . $invoice . ".json",
            json_encode([
                "invoice" => $invoice,
                "tenant"  => $tenantID,
                "details" => $details,
                "net"     => $net,
                "date"    => date("c")
            ], JSON_PRETTY_PRINT)
        );
    }
}
