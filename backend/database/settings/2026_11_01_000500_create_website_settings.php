<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Seeds the website copy from the approved "GEA Website v3" design and GEA's company profile.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $m = $this->migrator;

        $m->add('website.hero_lines', ['Moving Goods,', 'Building Trust,', 'Delivering Excellence.']);
        $m->add('website.hero_intro', 'GEA Haulage and Logistics is a transportation and logistics company committed to providing reliable, efficient, and professional haulage services.');

        $m->add('website.about_heading', 'Every delivery is a commitment.');
        $m->add('website.about_paragraphs', [
            'We understand that transportation is a vital part of every successful business, which is why we focus on delivering goods safely, on time, and with the highest level of responsibility.',
            'Our company is built on the principles of integrity, professionalism, safety, and customer satisfaction. We aim to provide dependable logistics solutions that help businesses move their goods efficiently while building lasting relationships with our clients and partners.',
        ]);
        $m->add('website.about_quote', 'At GEA Haulage and Logistics, we believe that every delivery is a commitment, every client is a valued partner, and every journey is an opportunity to demonstrate excellence.');

        $m->add('website.story_lead', 'GEA Haulage and Logistics was established with a clear purpose: to provide dependable transportation and logistics services that businesses can trust.');
        $m->add('website.story_paragraphs', [
            'Recognizing the importance of timely and secure goods transportation, we are committed to helping businesses overcome logistics challenges through professional service, careful planning, and a customer-focused approach.',
            'Our journey is driven by a passion for service excellence and a determination to contribute to the growth of businesses through reliable haulage solutions. As we continue to grow, our commitment remains the same: to move goods responsibly, serve our customers faithfully, and build a reputation founded on trust and excellence.',
        ]);
        $m->add('website.mission', "To provide safe, reliable, efficient, and cost-effective haulage and logistics services that meet our customers' needs, support business growth, and deliver exceptional value through professionalism and integrity.");
        $m->add('website.vision', 'To become one of the most trusted and respected haulage and logistics companies in Nigeria, recognized for service excellence, operational efficiency, safety, and customer satisfaction.');

        $m->add('website.services_intro', 'GEA Haulage and Logistics aims to provide transportation and logistics solutions tailored to the needs of businesses and individuals, including:');

        $m->add('website.values', [
            ['title' => 'Integrity', 'text' => 'We conduct our business with honesty, transparency, and accountability.'],
            ['title' => 'Reliability', 'text' => 'We strive to ensure that every delivery is handled with care and responsibility.'],
            ['title' => 'Safety', 'text' => 'We prioritize the safe handling and transportation of goods throughout every journey.'],
            ['title' => 'Professionalism', 'text' => 'We maintain high standards in our operations, communication, and customer relationships.'],
            ['title' => 'Customer Satisfaction', 'text' => "We are committed to understanding our customers' needs and providing services that meet their expectations."],
            ['title' => 'Excellence', 'text' => 'We continuously seek better ways to improve our services and deliver outstanding results.'],
        ]);

        $m->add('website.reasons', [
            'Commitment to timely deliveries.',
            'Careful handling and transportation of goods.',
            'Professional customer service.',
            'Dependable and flexible logistics solutions.',
            'Strong business ethics and accountability.',
            'A commitment to building long-term customer relationships.',
        ]);
        $m->add('website.reasons_closing', 'Our goal is not simply to transport goods but to provide peace of mind and confidence throughout the delivery process.');

        $m->add('website.commitment_statement', 'We are committed to delivering more than goods; we deliver trust, reliability, and value.');
        $m->add('website.commitment_paragraphs', [
            'Every assignment we undertake is an opportunity to demonstrate our dedication to quality service and customer satisfaction.',
            'At GEA Haulage and Logistics, we believe that successful logistics is built on trust, strengthened by professionalism, and sustained through consistent performance.',
        ]);

        $m->add('website.steps', [
            ['title' => 'Request a quote', 'text' => 'Tell us the cargo, pickup and delivery points, and preferred date by form, phone or WhatsApp.'],
            ['title' => 'Confirm and book', 'text' => 'We match the right truck from our 5–30 tonne fleet and confirm price and schedule.'],
            ['title' => 'Pickup and tracking', 'text' => 'Your goods are loaded with care and you receive a GEA tracking code for the journey.'],
            ['title' => 'Delivery', 'text' => 'Cargo arrives at destination and delivery is confirmed with you.'],
        ]);

        $m->add('website.careers_intro', "We are growing our team of professional drivers and support staff. If you take safety seriously and know Nigeria's roads, we want to hear from you.");
        $m->add('website.quote_intro', 'GEA Haulage and Logistics is committed to becoming a dependable partner in the transportation and logistics industry. Through integrity, professionalism, and a customer-first approach, we aim to support businesses, strengthen commercial connections, and contribute to economic growth.');

        $m->add('website.show_clients', false);
        $m->add('website.credentials', [
            ['title' => 'CAC registration', 'detail' => 'RC 9198613'],
        ]);

        $m->add('website.email_info', 'info@geahaulageandlogistics.com');
        $m->add('website.email_sales', 'sales@geahaulageandlogistics.com');
        $m->add('website.email_operations', 'logistic@geahaulageandlogistics.com');
        $m->add('website.email_support', 'support@geahaulageandlogistics.com');
        $m->add('website.email_billing', 'billing@geahaulageandlogistics.com');
    }
};
