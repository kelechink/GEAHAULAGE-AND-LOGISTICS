<div class="panel">
    @if (session('quote_sent'))
        <div class="done" role="status">
            <span class="tick" aria-hidden="true">✓</span>
            <h3>Thank you, {{ session('quote_sent') }}.</h3>
            <p class="lede">We received your quote request. Our team will contact you within one working day with a price.</p>
            <a class="btn btn-outline" href="{{ route('quote') }}" style="align-self:flex-start">Send another request</a>
        </div>
    @else
        <form class="form" method="post" action="{{ route('quote.store') }}" novalidate>
            @csrf
            <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'name', 'label' => 'Full name', 'required' => true, 'autocomplete' => 'name'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'company', 'label' => 'Company (optional)', 'autocomplete' => 'organization'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'autocomplete' => 'email'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'from', 'label' => 'Pickup location', 'required' => true, 'placeholder' => 'e.g. Apapa, Lagos'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'to', 'label' => 'Delivery location', 'required' => true, 'placeholder' => 'e.g. Kano'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'service', 'label' => 'Service', 'type' => 'select', 'full' => true, 'options' => $services->pluck('title')->all(), 'value' => $selectedService ?? '', 'placeholderOption' => 'Choose a service'])
            @include('site.partials.field', ['prefix' => 'q', 'name' => 'details', 'label' => 'Cargo details', 'type' => 'textarea', 'full' => true, 'placeholder' => 'Type of goods, weight or volume, preferred date'])
            <label class="portal-check full"><input type="checkbox" name="consent_marketing" value="1" @checked(old('consent_marketing'))> Send me occasional news and offers from GEA (optional)</label>
            <button class="btn btn-signal full" type="submit" style="min-height:58px;font-size:17px">Send quote request</button>
            <p class="fine full">We use your details only to respond to this request. See our <a href="{{ route('privacy') }}" style="text-decoration:underline">privacy policy</a>.</p>
        </form>
    @endif
</div>
