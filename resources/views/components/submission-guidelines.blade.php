@php $id = app()->getLocale() === 'id'; @endphp
<div class="space-y-8">
    <x-deadline-summary />
    <div class="prose prose-slate max-w-none">
        <h2>{{ __('site.guide_abstract_and_authors') }}</h2>
        <p>{{ __('site.guide_write_an_english_abstract_of') }}</p>
        <h2>{{ __('site.guide_review_and_acceptance') }}</h2>
        <p>{{ __('site.guide_save_your_draft_and_check') }}</p>
        <h2>{{ __('site.guide_invoice_and_payment') }}</h2>
        <p>{{ __('site.guide_after_the_loa_is_issued') }}</p>
        <h2>{{ __('site.guide_preparing_the_full_paper') }}</h2>
        <p>{{ __('site.public_full_paper_instructions') }}</p>
        <p>{{ __('site.guide_the_full_paper_deadline_is') }}</p>
        <h2>{{ __('site.guide_presentation_and_certificates') }}</h2>
        <p>{{ __('site.guide_follow_the_schedule_in_central') }}</p>
    </div>
    <div class="flex flex-wrap gap-3">
        @if(manuscriptTemplatePath())
            <a href="{{ route('manuscript-template') }}" class="btn btn-primary">{{ __('site.guide_download_manuscript_template_docx') }}</a>
        @else
            <p class="w-full rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">{{ __('site.public_template_pending') }}</p>
        @endif
        <a href="{{ route('contact') }}" class="btn btn-outline">{{ __('site.guide_contact_the_committee') }}</a>
    </div>
</div>
