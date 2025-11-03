-- Update SILA home page content to match reference design and add Arabic content
UPDATE cms_page 
SET content = '<div class="home-page sila-home">
    <div class="container">
        <div class="top-home">
            <div class="slidershow">
                <div class="slidershow-type-1 hover-to-show">
                    <div data-owl="owl-slider" data-autoplay="true" data-nav="true" data-dots="false" data-screen0="1"
                        data-screen481="1" data-screen768="1" data-screen992="1" data-screen1200="1" data-screen1441="1"
                        data-screen1681="1" data-screen1920="1" data-margin="1" data-autoplayhoverpause="true"
                        data-loop="true" data-center="false" data-stagepadding="0" data-mousedrag="true"
                        data-touchdrag="true">
                        <div class="owl-carousel owl-theme">
                            <div class="item">
                                <a href="https://technostationery.com/sila/">
                                    <img src="{{media url=wysiwyg/sila.PNG}}"
                                        alt="SILA" width="620" height="422" />
                                </a>
                            </div>
                            
                            <div class="item">
                                <a href="https://technostationery.com/sila/ar/book_search/">
                                    <img src="{{media url=wysiwyg/sila.PNG}}"
                                        alt="بحث الكتب" width="620" height="422" />
                                </a>
                            </div>
                        </div>
                        <div class="loading-content"><span class="hidden">جاري التحميل...</span></div>
                    </div>
                </div>
            </div>

            <div class="right-banner">
                <div class="banner-image">
                    <a href="https://technostationery.com/sila/ar/book_search/" target="_blank">
                        <img src="{{media url=wysiwyg/sila-logo-1.png}}"
                            alt="بحث الكتب" width="260" height="140" />
                    </a>
                </div>

                <div class="banner-image">
                    <a href="https://technostationery.com/sila/ar/sila_history/" target="_blank">
                        <img src="{{media url=wysiwyg/sila-logo-1.png}}"
                            alt="تاريخ الشركة" width="260" height="140" />
                    </a>
                </div>

                <div class="banner-image">
                    <a href="https://technostationery.com/sila/" target="_blank">
                        <img src="{{media url=wysiwyg/sila-logo-1.png}}"
                            alt="SILA" width="270" height="144" />
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="featured-products">
        <div class="container">
            <h2 class="section-title">منتجات مميزة</h2>
            <div class="products-grid">
                <div class="product-item">
                    <h3>الأدوات المدرسية</h3>
                    <p>مجموعة شاملة من الأدوات المدرسية للطلاب والمعلمين</p>
                </div>
                
                <div class="product-item">
                    <h3>المنتجات المكتبية</h3>
                    <p>أدوات ومستلزمات للمكاتب والشركات</p>
                </div>
                
                <div class="product-item">
                    <h3>الفنون والحرف</h3>
                    <p>أدوات ومستلزمات للفنانين ومحبي الحرف اليدوية</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="about-sila">
        <div class="container">
            <h2 class="section-title">لماذا SILA؟</h2>
            <div class="features">
                <div class="feature-item">
                    <h3>جودة عالية</h3>
                    <p>جميع منتجاتنا تتميز بجودة عالية ومتانة</p>
                </div>
                
                <div class="feature-item">
                    <h3>أسعار تنافسية</h3>
                    <p>نوفر أفضل الأسعار في السوق الجزائري</p>
                </div>
                
                <div class="feature-item">
                    <h3>توصيل سريع</h3>
                    <p>خدمة توصيل سريعة وموثوقة في جميع أنحاء الجزائر</p>
                </div>
                
                <div class="feature-item">
                    <h3>خدمة عملاء ممتازة</h3>
                    <p>فريق دعم متخصص لتقديم أفضل خدمة للعملاء</p>
                </div>
            </div>
        </div>
    </div>
</div>'
WHERE identifier = 'home' AND page_id = 68;