/*=============== HIDE & SHOW PASSWORD ===============*/
const showHiddenPass = (passwordId, eyeId) => {
   const input = document.getElementById(passwordId);
   const iconEye = document.getElementById(eyeId);

   iconEye.addEventListener('click', () => {
      // Change password to text
      if (input.type === 'password') {
         input.type = 'text';
         iconEye.classList.remove('ri-eye-line');
         iconEye.classList.add('ri-eye-off-line');
      } else {
         input.type = 'password';
         iconEye.classList.remove('ri-eye-off-line');
         iconEye.classList.add('ri-eye-line');
      }
   });
}
showHiddenPass('loginPass', 'loginEye');

/*=============== SWIPER IMAGES ===============*/
const swiperLogin = new Swiper('.login__swiper', {
   loop: true,
   spaceBetween: 24,
   grabCursor: true,
   speed: 800,
   effect: 'fade',
   fadeEffect: {
      crossFade: true
   },

   pagination: {
      el: '.swiper-pagination',
      clickable: true,
   },

   autoplay: {
      delay: 4000,
      disableOnInteraction: false,
      pauseOnMouseEnter: true,
   },

   // Add parallax effect
   on: {
      init: function() {
         this.slides.forEach((slide) => {
            slide.style.transform = 'scale(1)';
         });
      }
   }
});

/*=============== FORM SUBMISSION WITH LOADER ===============*/
const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');
const loginSpinner = document.getElementById('loginSpinner');
const loginBtnText = loginBtn.querySelector('span');

if (loginForm) {
   loginForm.addEventListener('submit', (e) => {
      // Show loading state
      loginBtn.disabled = true;
      loginBtnText.style.opacity = '0';
      loginSpinner.style.display = 'inline-block';
      
      // Form will submit normally
   });
}

/*=============== ALERT DISMISS ===============*/
const alertCloseBtns = document.querySelectorAll('.alert-close');

alertCloseBtns.forEach(btn => {
   btn.addEventListener('click', function() {
      const alert = this.parentElement;
      alert.style.animation = 'slideDown 0.4s reverse';
      setTimeout(() => {
         alert.remove();
      }, 400);
   });
});

// Auto dismiss alerts after 5 seconds
setTimeout(() => {
   const alerts = document.querySelectorAll('.alert');
   alerts.forEach(alert => {
      alert.style.animation = 'slideDown 0.4s reverse';
      setTimeout(() => {
         alert.remove();
      }, 400);
   });
}, 5000);

/*=============== INPUT ANIMATIONS ===============*/
const loginInputs = document.querySelectorAll('.login__input');

loginInputs.forEach(input => {
   // Add focus effect
   input.addEventListener('focus', function() {
      const box = this.closest('.login__box');
      const icon = box.querySelector('i:not(.login__eye)');
      icon.style.color = 'var(--first-color)';
   });

   input.addEventListener('blur', function() {
      const box = this.closest('.login__box');
      const icon = box.querySelector('i:not(.login__eye)');
      if (!this.value) {
         icon.style.color = 'var(--text-color)';
      }
   });

   // Add floating label effect (optional)
   if (input.value) {
      const box = input.closest('.login__box');
      const icon = box.querySelector('i:not(.login__eye)');
      icon.style.color = 'var(--first-color)';
   }
});

/*=============== REMEMBER ME CHECKBOX STYLING ===============*/
// Add custom styling for checkbox (optional)
const rememberCheckbox = document.querySelector('.login__checkbox');
if (rememberCheckbox) {
   rememberCheckbox.addEventListener('change', function() {
      if (this.checked) {
         this.style.accentColor = 'var(--first-color)';
      }
   });
}

/*=============== ADD PARALLAX EFFECT TO SWIPER (OPTIONAL) ===============*/
const swiperContainer = document.querySelector('.login__swiper');
if (swiperContainer) {
   swiperContainer.addEventListener('mousemove', (e) => {
      const x = e.clientX / window.innerWidth;
      const y = e.clientY / window.innerHeight;
      
      const slides = document.querySelectorAll('.swiper-slide-active');
      slides.forEach(slide => {
         slide.style.transform = `scale(1.02) translate(${x * 10}px, ${y * 10}px)`;
      });
   });

   swiperContainer.addEventListener('mouseleave', () => {
      const slides = document.querySelectorAll('.swiper-slide-active');
      slides.forEach(slide => {
         slide.style.transform = 'scale(1) translate(0, 0)';
      });
   });
}

/*=============== PREVENT FORM RESUBMISSION ON PAGE REFRESH ===============*/
if (window.history.replaceState) {
   window.history.replaceState(null, null, window.location.href);
}

/*=============== ADD LOADING CLASS TO BODY ===============*/
window.addEventListener('load', () => {
   document.body.classList.add('loaded');
});