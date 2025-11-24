window.addEventListener('DOMContentLoaded', (event) => {
  
  // ----- Demos -----
  // Class updates
  const classUpdate = document.querySelectorAll('.class-update');

  for (const item of classUpdate) {
    let target = item.dataset['target'];
    if (!target) return;

    let targetElement = document.querySelectorAll(target);
    for (const item of targetElement) {
      item.classUpdate = {};
      item.classUpdate.classCurrent = [...item.classList];
    }

    const classUpdateTrigger = item.querySelectorAll('select');

    for (const item of classUpdateTrigger) {
  
      item.addEventListener('change', (event) => {
        let oldClass = [];
        for (const option of event.target.options) {
          oldClass.push(option.value.split(' '))
        }
        oldClass = oldClass.flat();
  
        let newClass = event.target.value.split(' ');
  
        for (const demo of targetElement) {
          let oldClassToApply = oldClass.filter(item => !demo.classUpdate.classCurrent.includes(item));
          let newClassToApply = newClass.filter(item => !demo.classUpdate.classCurrent.includes(item));
  
          for (const item of oldClassToApply) {
            let cssClass = item;
            if (cssClass) demo.classList.remove(item);
          }        
          
          for (const item of newClassToApply) {
            let cssClass = item;
            if (cssClass) demo.classList.add(item);
          }
        }
      })
      
    };
  };



  // Animation demo
  const demoAnimButtons = document.querySelectorAll('.progress-tracker--anim button');

  for (const item of demoAnimButtons) {
    item.addEventListener('click', (event) => {
      event.preventDefault();

      for (const item of demoAnimButtons) {
        item.closest('.progress-step').classList.remove('is-active');
        item.closest('.progress-step').removeAttribute('aria-current');
      }
      
      let step = item.closest('.progress-step');

      if(!step.classList.contains('is-complete')) {
        step.classList.add('is-complete');
        if(step.nextElementSibling !== null) {
          step.nextElementSibling.classList.add('is-active');
          step.nextElementSibling.setAttribute('aria-current', 'step');
        }
      }
      else {
        step.classList.remove('is-complete');
        if(step.previousElementSibling !== null) {
          step.previousElementSibling.classList.remove('is-active');
          step.previousElementSibling.removeAttribute('aria-current');
        }
      }
    });
  }



  
  // ----- Site -----

  // Encoded text
  const encodeElements = document.querySelectorAll('.encode');
  for (const item of encodeElements) {
    let decode = atob(item.dataset['encode']);

    if (item.dataset['encodeAttribute']) {
      item.setAttribute(`${item.dataset['encodeAttribute']}`, `${decode}`);
    }
  }

  // Observe header height
  const observeHeader = function() {
    let element = document.querySelector('.header');
    let height = 0;

    const resizeObserver = new ResizeObserver((entries) => {
      for (const entry of entries) {
        let heightNew = entry.contentBoxSize[0].blockSize;

        if (height !== heightNew) {
          height = entry.contentBoxSize[0].blockSize;
  
          document.documentElement.style.setProperty(`--header-height`, `${height}px`);            
        } 
      }
  
    });
  
    resizeObserver.observe(element);
  }

  observeHeader();

});
