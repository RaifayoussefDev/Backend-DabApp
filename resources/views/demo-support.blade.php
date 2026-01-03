<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DabApp - Support Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero-section {
            background: linear-gradient(135deg, #101828 0%, #F03D24 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            font-size: 3rem;
            color: #F03D24;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="hero-section">
        <div class="container">
            <h1 class="mb-3">DabApp Support Center</h1>
            
            {{-- AUTHENTICATION SECTION --}}
            <div class="card mx-auto p-4 shadow-sm text-dark mt-4" style="max-width: 400px; background: rgba(255,255,255,0.95);">
                @if(session('success'))
                    <div class="alert alert-success py-2">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger py-2">{{ session('error') }}</div>
                @endif

                @auth
                    <div class="text-center">
                        <h5 class="mb-3">👋 Hello, {{ Auth::user()->name }}!</h5>
                        <p class="small text-muted mb-3">{{ Auth::user()->email }}</p>
                        <form action="{{ route('demo-support.logout') }}" method="POST">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm">Sign Out</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('demo-support.login') }}" method="POST">
                        @csrf
                        <h5 class="mb-3">Login to simulate User</h5>
                        <div class="mb-2">
                            <input type="email" name="email" class="form-control" placeholder="Email" required value="yucefr@gmail.com"> 
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <button class="btn btn-primary w-100">Login for Demo</button>
                    </form>
                @endauth
            </div>
            {{-- END AUTHENTICATION SECTION --}}

            <p class="lead mt-4">We are here to help you via Chat!</p>
        </div>
    </div>

    <div class="container my-5">
        <div class="row text-center">
            <div class="col-md-4">
                <div class="card p-4">
                    <div class="icon-box">💬</div>
                    <h3>Live Chat</h3>
                    <p>Click the bubble in the bottom right to chat with us instantly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4">
                    <div class="icon-box">📧</div>
                    <h3>Email Us</h3>
                    <p>support@dabapp.com</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4">
                    <div class="icon-box">📚</div>
                    <h3>Documentation</h3>
                    <p>Read our guides and FAQs.</p>
                </div>
            </div>
        </div>
        
        <div class="row mt-5 justify-content-center">
            <div class="col-md-8 text-center">
                <div class="alert alert-info">
                    <strong>Demo Note:</strong> The Tawk.to widget should appear in the bottom right corner.
                    <br>
                    (If you haven't pasted the script yet, you won't see it!)
                </div>
            </div>
        </div>
    </div>

    <!-- TAWK.TO SCRIPT START -->
    <script type="text/javascript">
    var Tawk_API=Tawk_API||{};
    
    // Configurer l'identité AVANT le chargement
    Tawk_API.visitor = {
        name: "{{ Auth::check() ? Auth::user()->name : 'Visiteur Test (Demo)' }}",
        email: "{{ Auth::check() ? Auth::user()->email : 'test@demo.com' }}",
        phone: "{{ Auth::check() ? Auth::user()->phone : '+00000000' }}"
    };

    // Forcer la mise à jour une fois chargé (utile si cache)
    Tawk_API.onLoad = function(){
        Tawk_API.setAttributes({
            name: "{{ Auth::check() ? Auth::user()->name : 'Visiteur Test (Demo)' }}",
            email: "{{ Auth::check() ? Auth::user()->email : 'test@demo.com' }}",
            phone: "{{ Auth::check() ? Auth::user()->phone : '+00000000' }}",
            hash: "{{ Auth::check() ? hash_hmac('sha256', Auth::user()->email, 'YOUR_API_KEY_IF_NEEDED') : '' }}"
        }, function(error){});
    };

    var Tawk_LoadStart=new Date();
    (function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/695831089a62c119792a9bf5/1je07v318';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
    })();
    </script>
    <!-- TAWK.TO SCRIPT END -->

</body>
</html>
