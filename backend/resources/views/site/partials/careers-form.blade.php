<div class="panel">
    @if (session('applied'))
        <div class="done" role="status">
            <h3>Application received</h3>
            <p class="lede">Thank you, {{ session('applied') }}. Our operations team will call you if your profile matches an opening.</p>
            <a class="btn btn-outline" href="{{ route('careers') }}" style="align-self:flex-start">Submit another application</a>
        </div>
    @else
        <form class="form" method="post" action="{{ route('careers.store') }}" novalidate>
            @csrf
            <h3 class="form-title full">Apply now</h3>
            <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('site.partials.field', ['prefix' => 'c', 'name' => 'name', 'label' => 'Full name', 'required' => true, 'autocomplete' => 'name'])
            @include('site.partials.field', ['prefix' => 'c', 'name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel'])
            @include('site.partials.field', ['prefix' => 'c', 'name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'options' => $roles->pluck('title')->all(), 'placeholderOption' => 'Choose a role'])
            @include('site.partials.field', ['prefix' => 'c', 'name' => 'years', 'label' => 'Years of experience', 'type' => 'number', 'min' => 0, 'max' => 50, 'inputmode' => 'numeric'])
            @include('site.partials.field', ['prefix' => 'c', 'name' => 'licence', 'label' => "Driver's licence class (drivers only)", 'full' => true, 'placeholder' => 'As shown on your FRSC licence'])
            @include('site.partials.field', ['prefix' => 'c', 'name' => 'about', 'label' => 'About you', 'type' => 'textarea', 'full' => true, 'placeholder' => 'Routes you know, trucks you have driven, current location'])
            <button class="btn btn-signal full" type="submit" style="min-height:56px;font-size:17px">Send application</button>
            <p class="fine full">We use your details only to assess your application. See our <a href="{{ route('privacy') }}" style="text-decoration:underline">privacy policy</a>.</p>
        </form>
    @endif
</div>
