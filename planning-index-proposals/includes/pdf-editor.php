<?php
if (!defined('ABSPATH')) exit;

class PI_PDF_Editor {

    public static function generate_or_update($tmpl_key, $data, $output_path, $is_update = false) {
        try {
            // Ensure output directory exists and is writable
            $dir = dirname($output_path);
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    error_log("PI_PDF_Editor: Failed to create directory {$dir}");
                    return false;
                }
            }

            // Create mPDF instance
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 25,
                'margin_bottom' => 25,
                'margin_header' => 0,
                'margin_footer' => 5,
            ]);

            // Prepare HTML template
            $tmpl_html = PI_PDF_TEMPLATES[$tmpl_key]['html'] ?? PI_PDF_TEMPLATES['basic']['html'];

            // === NEW CENTRALIZED REPLACEMENTS BLOCK ===
            $replacements = [
                '[company_name]'    => $data['company_name'] ?? '',
                '[company_address]' => $data['company_address'] ?? '',
                '[phone]'           => $data['phone'] ?? '',
                '[email]'           => $data['email'] ?? '',
                '[website]'         => $data['website'] ?? '',
                '[date]'            => $data['date'] ?? '',
                '[valid_until]'     => $data['valid_until'] ?? '',
                '[amount]'          => $data['amount'] ?? '',
                '[terms]'           => nl2br($data['terms'] ?? ''),
                '[warranty]'        => nl2br($data['warranty'] ?? ''),
                '[description]'     => nl2br($data['description'] ?? ''),
                '[address]'         => nl2br($data['address'] ?? ''),
                '[re_line]'         => $data['re_line'] ?? '',
                '[notes]'           => nl2br($data['notes'] ?? ''),
                '[logo]'            => $data['logo'] ? '<img src="' . esc_url($data['logo']) . '" style="max-height:80px;">' : '',
                '[signature]'       => $data['signature'] ? '<img src="' . esc_url($data['signature']) . '" style="max-height:60px;">' : '',
            ];

            foreach ($replacements as $placeholder => $value) {
                $tmpl_html = str_replace($placeholder, $value, $tmpl_html);
            }
            // === END OF REPLACEMENTS ===

            $mpdf->WriteHTML($tmpl_html);

            // Footer
            $footer = sprintf('%s | %s | %s | %s',
                esc_html($data['company_name'] ?? ''),
                esc_html($data['phone'] ?? ''),
                esc_html($data['email'] ?? ''),
                esc_html($data['website'] ?? '')
            );
            $mpdf->SetFooter($footer);

            // Write to temp file first, then rename atomically
            $tmp = $output_path . '.tmp.' . uniqid();
            $mpdf->Output($tmp, \Mpdf\Output\Destination::FILE);

            // Ensure tmp exists, then move/rename
            if (file_exists($tmp)) {
                @chmod($tmp, 0664);
                if (@rename($tmp, $output_path)) {
                    @chmod($output_path, 0664);
                    error_log("PI_PDF_Editor: PDF created/updated at {$output_path}");
                    return true;
                } else {
                    // Fallback: copy then unlink
                    if (@copy($tmp, $output_path)) {
                        @unlink($tmp);
                        @chmod($output_path, 0664);
                        error_log("PI_PDF_Editor: PDF created (copied) at {$output_path}");
                        return true;
                    } else {
                        error_log("PI_PDF_Editor: Failed to move tmp PDF {$tmp} to {$output_path}");
                        return false;
                    }
                }
            } else {
                error_log("PI_PDF_Editor: Temp PDF not created at {$tmp}");
                return false;
            }

        } catch (\Mpdf\MpdfException $e) {
            error_log('PI_PDF_Editor: mPDF exception - ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log('PI_PDF_Editor: Generation failed - ' . $e->getMessage());
            return false;
        }
    }

}