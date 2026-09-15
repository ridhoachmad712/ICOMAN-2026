@php $height = (int) $section->setting('height', 48); @endphp

<section aria-hidden="true" style="height: {{ max(0, min(400, $height)) }}px"></section>
