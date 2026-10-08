<div class="lang" role="group" aria-label="{{ __('Language') }}">
    <form method="POST" action="{{ route('admin.locale', 'fa') }}">@csrf<button class="{{ app()->getLocale() === 'fa' ? 'on' : '' }}" lang="fa">فارسی</button></form>
    <form method="POST" action="{{ route('admin.locale', 'en') }}">@csrf<button class="{{ app()->getLocale() === 'en' ? 'on' : '' }}" lang="en">English</button></form>
</div>
