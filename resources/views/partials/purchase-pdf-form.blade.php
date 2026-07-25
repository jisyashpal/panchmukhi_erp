<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="mb-0"><i class="bi bi-file-earmark-pdf me-1"></i>Generate PDF</h2>
    </div>
    <div class="admin-card-body">
        <p class="small text-muted mb-3">Customize the letterhead and terms shown on the purchase order export.</p>
        <form action="{{ $pdfRoute }}" method="GET" target="_blank" id="purchase_pdf_form">
            <div class="mb-2">
                <label class="form-label small mb-1" for="pdf_company_name">Company name</label>
                <input type="text" name="company_name" id="pdf_company_name" class="form-control form-control-sm"
                    value="{{ old('company_name', $pdfDefaults['company_name']) }}" required maxlength="200">
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1" for="pdf_company_address">Address</label>
                <textarea name="company_address" id="pdf_company_address" class="form-control form-control-sm" rows="3" maxlength="1000">{{ old('company_address', $pdfDefaults['company_address']) }}</textarea>
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1" for="pdf_company_gstin">GSTIN</label>
                <input type="text" name="company_gstin" id="pdf_company_gstin" class="form-control form-control-sm"
                    value="{{ old('company_gstin', $pdfDefaults['company_gstin']) }}" maxlength="20" placeholder="e.g. 22AAAAA0000A1Z5">
            </div>
            <div class="mb-3">
                <label class="form-label small mb-1" for="pdf_terms_and_conditions">Terms and conditions</label>
                <textarea name="terms_and_conditions" id="pdf_terms_and_conditions" class="form-control form-control-sm" rows="5" maxlength="5000"
                    placeholder="Add terms and conditions for this purchase order…">{{ old('terms_and_conditions', $pdfDefaults['terms_and_conditions'] ?? '') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">
                <i class="bi bi-file-earmark-arrow-down me-1"></i>Download / View PDF
            </button>
            @isset($excelRoute, $wordRoute)
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <button type="submit" class="btn btn-outline-success btn-sm w-100" formaction="{{ $excelRoute }}" formtarget="_self">
                        <i class="bi bi-file-earmark-excel me-1"></i>Excel
                    </button>
                </div>
                <div class="col-6">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100" formaction="{{ $wordRoute }}" formtarget="_self">
                        <i class="bi bi-file-earmark-word me-1"></i>Word
                    </button>
                </div>
            </div>
            @endisset
        </form>

        @isset($shareLinksRoute)
        <div class="border-top pt-3">
            <p class="small text-muted mb-2">Send PDF to <strong>{{ $supplier->name }}</strong>:</p>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_share_whatsapp"
                    @disabled(! $supplier->phone) data-locked="{{ $supplier->phone ? '0' : '1' }}">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp with PDF
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn_share_email"
                    @disabled(! $supplier->email) data-locked="{{ $supplier->email ? '0' : '1' }}">
                    <i class="bi bi-envelope me-1"></i>Email with PDF
                </button>
            </div>
            @if(! $supplier->phone || ! $supplier->email)
                <p class="small text-muted mt-2 mb-0">
                    @if(! $supplier->phone)
                        <span class="d-block">No phone on file — update supplier master to enable WhatsApp.</span>
                    @endif
                    @if(! $supplier->email)
                        <span class="d-block">No email on file — update supplier master to enable email.</span>
                    @endif
                </p>
            @endif
            <p class="small text-muted mt-2 mb-0">WhatsApp shares the PDF file directly on supported devices; otherwise the PDF downloads first.</p>
        </div>
        @endisset
    </div>
</div>

@isset($shareLinksRoute)
@push('scripts')
<script>
(function () {
    const pdfRoute = @json($pdfRoute);
    const emailRoute = @json($emailRoute ?? null);
    const shareLinksRoute = @json($shareLinksRoute);
    const pdfFilename = @json($pdfFilename ?? 'purchase-order.pdf');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    function headerPayload() {
        return {
            company_name: document.getElementById('pdf_company_name').value,
            company_address: document.getElementById('pdf_company_address').value,
            company_gstin: document.getElementById('pdf_company_gstin').value,
            terms_and_conditions: document.getElementById('pdf_terms_and_conditions')?.value || '',
        };
    }

    function headerQueryString() {
        return new URLSearchParams(headerPayload()).toString();
    }

    async function fetchPdfBlob() {
        const response = await fetch(`${pdfRoute}?${headerQueryString()}`, {
            headers: { 'Accept': 'application/pdf' },
        });

        if (!response.ok) {
            throw new Error('Could not generate PDF.');
        }

        return response.blob();
    }

    async function fetchShareLinks() {
        const response = await fetch(`${shareLinksRoute}?${headerQueryString()}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Could not prepare share message.');
        }

        return response.json();
    }

    function downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    async function shareWhatsApp() {
        const button = document.getElementById('btn_share_whatsapp');
        if (!button || button.disabled) {
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing PDF…';

        try {
            const [blob, links] = await Promise.all([fetchPdfBlob(), fetchShareLinks()]);
            const file = new File([blob], pdfFilename, { type: 'application/pdf' });
            const message = links.whatsapp_message || '';

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file],
                    text: message,
                    title: pdfFilename,
                });
                return;
            }

            downloadBlob(blob, pdfFilename);

            if (links.whatsapp) {
                window.open(links.whatsapp, '_blank');
            } else {
                alert('PDF downloaded. Please attach it manually in WhatsApp.');
            }
        } catch (error) {
            if (error?.name !== 'AbortError') {
                alert(error.message || 'Could not share on WhatsApp.');
            }
        } finally {
            if (button.dataset.locked !== '1') {
                button.disabled = false;
            }
            button.innerHTML = originalHtml;
        }
    }

    async function sendEmail() {
        const button = document.getElementById('btn_share_email');
        if (!button || button.disabled || !emailRoute) {
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';

        try {
            const response = await fetch(emailRoute, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(headerPayload()),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Could not send email.');
            }

            alert(data.message || 'Email sent with PDF attached.');
        } catch (error) {
            alert(error.message || 'Could not send email.');
        } finally {
            if (button.dataset.locked !== '1') {
                button.disabled = false;
            }
            button.innerHTML = originalHtml;
        }
    }

    document.getElementById('btn_share_whatsapp')?.addEventListener('click', shareWhatsApp);
    document.getElementById('btn_share_email')?.addEventListener('click', sendEmail);
})();
</script>
@endpush
@endisset
