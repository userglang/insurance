<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;
use TCPDF;
use Carbon\Carbon;

class MemberPrintPDFService
{
    protected Member $member;
    protected ?Subscription $subscription;
    protected array $config;

    public function __construct(Member $member, ?Subscription $subscription = null)
    {
        $this->member = $member;
        $this->subscription = $subscription;
        $this->config = $this->getDefaultConfig();
    }

    public function generatePDF(string $outputMode = 'I'): void
    {
        $pdf = $this->createPDF();
        $this->setupPDFProperties($pdf);
        $this->addContent($pdf);
        $filename = $this->generateFilename();
        $pdf->Output($filename, $outputMode);
    }

    protected function createPDF(): TCPDF
    {
        $config = $this->config;

        return new class($this->member, $this->subscription, $config) extends TCPDF {
            protected Member $member;
            protected ?Subscription $subscription;
            protected array $config;

            public function __construct(Member $member, ?Subscription $subscription, array $config)
            {
                parent::__construct('P', 'mm', 'letter', true, 'UTF-8', false);
                $this->member = $member;
                $this->subscription = $subscription;
                $this->config = $config;
            }

            public function Header()
            {
                $cfg = $this->config;
                $logo = public_path('images/OIC_Logo.jpg');
                if (file_exists($logo)) {
                    $this->Image($logo, 83, 4, 40, '', 'JPG', '', 'T', false, 100);
                }

                $this->Ln(20);
                $this->SetFont('freesans', 'B', 6);
                $this->Cell(178, 1, "\"{$cfg['company_slogan']}\"", 0, 1, 'C');
                $this->Cell(178, 1, $cfg['main_office'], 0, 1, 'C');

                $this->SetFont('freesans', 'B', 10);
                $branch = strtoupper($this->member->branch?->branch_name ?? 'DEFAULT') . ' BRANCH';
                $this->Cell(178, 1, $branch, 0, 1, 'C');

                $this->SetFont('freesans', '', 7);
                $branchAddr = $this->member->branch?->address ?? $cfg['main_office'];
                $this->Cell(178, 1, $branchAddr, 0, 1, 'C');

                $this->SetFont('freesans', 'B', 14);
                $this->Cell(178, 1, 'APPLICATION FORM', 0, 0, 'C');
            }

            public function Footer()
            {
                $this->SetY(-30);
                $this->SetFont('times', 'B', 12);
                $planholder = Auth::user()?->name ?? 'System';
                $this->Cell(90, 1, $planholder, 0, 0, 'L');

                $this->SetFont('times', '', 9);
                $this->Cell(30, 1, 'Noted/Approved By:', 0, 0, 'L');
                $this->Cell(60, 1, '______________________________', 0, 1, 'L');

                $this->Ln(2);
                $this->Cell(90, 1, 'Checked/Prepared By:', 0, 0, 'L');
                $this->Cell(60, 1, 'Branch Manager Name & Signature / Date', 0, 1, 'L');

                $this->Ln(5);
                $this->Cell(180, 5, str_repeat('-', 180), 0, 1, 'C');

                $this->SetFont('times', 'I', 8);
                $footer = "{$this->config['website']} | Page {$this->getAliasNumPage()} of {$this->getAliasNbPages()}";
                $this->Cell(199, 5, $footer, 0, false, 'R');
            }
        };
    }

    protected function setupPDFProperties(TCPDF $pdf): void
    {
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($this->config['company_name']);
        $pdf->SetTitle($this->config['company_name']);
        $pdf->SetSubject($this->config['company_name']);
        $pdf->SetKeywords('Pre-need, Life Plan, Oro Integrated');

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont('dejavusans', '', 14, '', true);
    }
    protected function addContent(TCPDF $pdf): void
    {
        $pdf->AddPage();
        $pdf->Ln(23);

        $createdAt = $this->member->created_at;
        $applicationDate = $createdAt ? $createdAt->format('F d, Y') : Carbon::now()->format('F d, Y');

        $pdf->SetFont('freesans', '', 8);
        $pdf->SetFillColor(224, 235, 255);
        $pdf->Cell(24, 1, 'Application Date: ', 0, 0, 'L');
        $pdf->Cell(42, 1, $applicationDate, 0, 0, 'L', 1);

        $subsDate = !empty($this->subscription?->activated_at) ? Carbon::parse($this->subscription?->activated_at) : null;
        $subsExpiry = !empty($this->subscription?->expires_at) ? Carbon::parse($this->subscription?->expires_at) : null;

        if ($subsDate && $subsExpiry && ($this->member->status ?? '') === 'accepted') {
            $pdf->Cell(24, 1, '', 0, 0);
            $pdf->Cell(25, 1, 'Plan Subscription:', 0, 0);
            $pdf->Cell(65, 1, "{$subsDate->format('F d, Y')} – {$subsExpiry->format('F d, Y')}", 0, 1, 'L', 1);
        } else {
            $pdf->Ln();
        }

        $pdf->Ln(1);
        $pdf->SetFont('freesans', 'B', 10);
        $pdf->Cell(180, 1, "PLANHOLDER'S NAME:", 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(18, 1, 'Surname:', 0, 0);
        $pdf->SetFont('freesans', 'B', 9);
        $pdf->Cell(42, 1, $this->member->last_name, 0, 0, 'L', 1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(18, 1, 'First Name:', 0, 0);
        $pdf->SetFont('freesans', 'B', 9);
        $pdf->Cell(42, 1, $this->member->first_name, 0, 0, 'L', 1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(22, 1, 'Middle Name:', 0, 0);
        $pdf->SetFont('freesans', 'B', 9);
        $pdf->Cell(38, 1, $this->member->middle_name ?? '', 0, 1, 'L', 1);

        $pdf->Ln(3);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Date of Birth:', 0, 0);
        $pdf->Cell(45, 1, $this->member->formatted_birth_date ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(10, 1, 'Age:', 0, 0);
        $pdf->Cell(10, 1, $this->member->age ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(10, 1, 'Sex:', 0, 0);
        $pdf->Cell(25, 1, $this->member->gender_label ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(20, 1, 'Nationality:', 0, 0);
        $pdf->Cell(35, 1, $this->member->nationality ?? 'Filipino', 0, 1, 'L', 1);

        // Place of Birth and Marital Status
        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Place of Birth:', 0, 0);
        $pdf->Cell(100, 1, $this->member->birth_place ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(20, 1, 'Civil Status:', 0, 0);
        $pdf->Cell(35, 1, $this->member->marital_status_label ?? '', 0, 1, 'L', 1);

        // Employment and Contact details
        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Occupation:', 0, 0);
        $pdf->Cell(65, 1, $this->member->occupation ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Office Number:', 0, 0);
        $pdf->Cell(65, 1, $this->member->office_contact_number ?? '', 0, 1, 'L', 1);

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(26, 1, 'Name of Employer:', 0, 0);
        $pdf->Cell(64, 1, $this->member->name_of_employer ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(27, 1, 'Employment Status:', 0, 0);
        $pdf->Cell(63, 1, $this->member->employment_status ?? '', 0, 1, 'L', 1);

        $officeAddr = $this->member->office_address ?? '';
        if (strlen($officeAddr) > 38) {
            $officeAddr = substr($officeAddr, 0, 35) . '...';
        }

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Office Address:', 0, 0);
        $pdf->Cell(65, 1, $officeAddr, 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Email Address:', 0, 0);
        $pdf->Cell(65, 1, $this->member->email ?? '', 0, 1, 'L', 1);

        $pdf->Ln(2);
        $pdf->SetFont('freesans', 'B', 10);
        $pdf->Cell(180, 1, 'RESIDENTIAL ADDRESS:', 0, 1);

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'House No.:', 0, 0);
        $pdf->Cell(65, 1, $this->member->house_number ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'SSS/GSIS No.:', 0, 0);
        $pdf->Cell(65, 1, $this->member->sss_gsis ?? '', 0, 1, 'L', 1);

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Street:', 0, 0);
        $pdf->Cell(65, 1, $this->member->street ?? '', 0, 0, 'L', 1);

        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'TIN:', 0, 0);
        $pdf->Cell(65, 1, $this->member->tin ?? '', 0, 1, 'L', 1);

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Barangay:', 0, 0);
        $pdf->Cell(155, 1, $this->member->barangay ?? '', 0, 1, 'L', 1);

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'City/Municipality:', 0, 0);
        $pdf->Cell(65, 1, $this->member->city ?? '', 0, 0, 'L', 1);
        $pdf->Cell(25, 1, 'Province:', 0, 0);
        $pdf->Cell(65, 1, $this->member->province ?? '', 0, 1, 'L', 1);

        $pdf->Ln(1);
        $pdf->SetFont('freesans', '', 8);
        $pdf->Cell(25, 1, 'Zip Code:', 0, 0);
        $pdf->Cell(65, 1, $this->member->zipcode ?? '', 0, 0, 'L', 1);
        $pdf->Cell(25, 1, 'Contact No.:', 0, 0);
        $pdf->Cell(65, 1, $this->member->contact_number ?? '', 0, 1, 'L', 1);

        $pdf->Ln(2);
        $pdf->Cell(180, 5, str_repeat('-', 180), 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetFont('freesans', 'B', 12);
        $pdf->Cell(180, 1, 'AUTHORITY TO DEDUCT', 0, 1, 'C');
        $pdf->SetFont('freesans', '', 9);

        $pdf->Ln(4);
        $pdf->SetFont('freesans', 'B', 9);
        $paymentDate = $subsDate ? $subsDate->format('F d, Y') : Carbon::now()->format('F d, Y');
        $pdf->Cell(180, 1, "Payment Date: {$paymentDate}", 0, 1, 'L');

        $pdf->Ln(2);
        $pdf->SetFont('freesans', '', 9);
        $pdf->Cell(175, 1, 'Please debit or transfer in order of fund availability from my:', 0, 1, 'L');

        $pdf->SetFont('freesans', 'B', 12);
        $acctName = $this->subscription?->productAccount?->product_name ?? 'Account Name';
        $acctNumber = $this->subscription?->productAccount?->account_number ?? 'Account Number';
        $pdf->Cell(175, 1, "{$acctName} – Account No. {$acctNumber}", 0, 1, 'L');

        $pdf->SetFont('freesans', '', 9);
        $amount = number_format($this->subscription?->amount  ?? 0, 2);
        $pdf->Cell(175, 1, "in the amount of ₱{$amount} as annual contribution for Pre‑need life plan.", 0, 1, 'L');

        $this->addSupportingImages($pdf);
    }

    protected function addSupportingImages(TCPDF $pdf): void
    {
        $sig = $this->member->signature;
        if ($sig && file_exists($path = public_path("upload/signature/{$sig}"))) {
            $pdf->Image($path, 12, 168, 94, 87, '', '', 'T', false, 100);
        }

        $vid = $this->member->idValid;
        if ($vid && file_exists($path = public_path("upload/valid_id/{$vid}"))) {
            $pdf->Image($path, 107, 168, 94, 87, '', '', 'T', false, 100);
        }
    }

    protected function generateFilename(): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9]/', '_', $this->member->full_name);
        return "member_profile_{$safe}_{$this->member->id}.pdf";
    }

    protected function getDefaultConfig(): array
    {
        return [
            'company_name' => 'Oro Integrated Cooperative',
            'company_slogan' => 'WHERE FINANCIAL FREEDOM BEGINS',
            'main_office' => 'Tiano Yacapin St., Brgy. 11, Cagayan de Oro City, 9000 Misamis Oriental, Philippines',
            'website' => 'www.orointegrated.coop',
        ];
    }

    public function savePDF(string $directory = 'storage/app/pdfs'): string
    {
        $pdf = $this->createPDF();
        $this->setupPDFProperties($pdf);
        $this->addContent($pdf);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filepath = "{$directory}/{$this->generateFilename()}";
        $pdf->Output($filepath, 'F');
        return $filepath;
    }

    public function getMemberSummary(): array
    {
        return [
            'id' => $this->member->id,
            'name' => $this->member->full_name,
            'branch' => $this->member->branch?->branch_name ?? 'Default Branch',
            'application_date' => $this->member->created_at?->format('F d, Y') ?? '',
            'has_signature' => !empty($this->member->signature),
            'has_valid_id' => !empty($this->member->idValid),
            'subscription_amount' => $this->subscription?->amount ?? 0,
        ];
    }
}
