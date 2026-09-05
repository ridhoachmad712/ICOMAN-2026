<?php

return json_decode(<<<'JSON'
{
  "common": [
    {
      "title": "Agreement",
      "body": "By registering, you confirm that you have read, understood, and accepted these participation terms."
    },
    {
      "title": "Registration details",
      "body": "Provide accurate details. One account is for one person. Undergraduate students select Undergraduate; postgraduate students and general attendees select General; overseas participants select International. The committee may request proof of eligibility and correct an inaccurate fee category."
    },
    {
      "title": "Payment",
      "body": "Fees follow the selected category, plus an optional publication fee where applicable. Check the invoice breakdown and total before paying. For USD prices, the invoice states a fixed exchange rate and the IDR billing total. Registration is valid after payment is verified. Payments are non-refundable."
    },
    {
      "title": "Event access and materials",
      "body": "Zoom access is personal. Do not share access, record, or redistribute materials without written permission from the committee and the material owner."
    },
    {
      "title": "Code of conduct",
      "body": "Harassment, hate speech, and disruption are prohibited. Violations may result in cancellation of participation without a refund."
    },
    {
      "title": "Data use",
      "body": "Data is used to manage accounts, reviews, registration, payments, and the conference. Read the Privacy and data use page for details and committee contact information."
    },
    {
      "title": "Changes and committee authority",
      "body": "The committee may change the schedule, program, or format when necessary, including force majeure. The committee may cancel registrations that breach these terms or contain false information."
    }
  ],
  "presenter": [
    {
      "title": "Presenter journey",
      "body": "Submit your abstract and complete review and any requested revisions. Committee acceptance automatically issues the LOA. Then choose a journal option if offered, check the invoice, pay, and upload the full paper."
    },
    {
      "title": "Originality and research ethics",
      "body": "The abstract and full paper must be original, free from plagiarism, fabrication, and falsification, and not under review or publication elsewhere. You are responsible for data permissions and all co-authors’ consent."
    },
    {
      "title": "Abstract and review",
      "body": "The abstract must be in English and contain 150–500 words. Reviewers assess it and make recommendations; the committee decides acceptance, revision, or rejection. Reviewer and committee decisions are final."
    },
    {
      "title": "Publication option",
      "body": "If recommended by reviewers, a SINTA 3 option is available at the additional fee displayed on the invoice. The regular option adds no fee. Choose after the LOA is issued and before starting payment. A recommendation does not guarantee publication; the manuscript remains subject to the target journal’s editorial process."
    },
    {
      "title": "Full paper and presentation",
      "body": "After payment, upload the full paper according to the guidelines and announced deadline. The committee may request revisions. Late or missing full papers forfeit publication eligibility without a refund; participation remains valid. Presenters must attend and present according to the schedule."
    },
    {
      "title": "Publication and certificates",
      "body": "You authorize processing of the manuscript for publication and indexing and warrant that it does not infringe third-party intellectual property rights. Presenter certificates require completed payment, presentation, and full paper submission."
    }
  ],
  "participant": [
    {
      "title": "Attendee certificate",
      "body": "An e-certificate is provided to registered attendees who attend the main Keynote and Plenary sessions."
    }
  ]
}
JSON, true);
