(function (Drupal, once) {
  Drupal.behaviors.customButtonBehavior = {
    attach(context, settings) {
      const elements = once('customButtonBehavior', '.ckeditor5-dependency-install', context);
      elements.forEach((element) => {
        element.addEventListener('click', (e) => {
          e.preventDefault();
          const composerPackage = e.target.dataset.package + ":" + e.target.dataset.packageVersion;
          const t = performInstall(e, composerPackage);
        });
      });
    },
  };
})(Drupal, once);

async function performInstall(event, composerPackage) {
  disableButtons();
  event.target.classList.add('is-installing');
  const reloadButton = event.target.closest('#ckeditor5-dependency-install-container').querySelector('.ckeditor5-dependency-install-reload-button');
  const throbberMessageElement = event.target.parentElement.querySelector('.ajax-progress__message');
  let throbberMessage = throbberMessageElement ? throbberMessageElement.innerHTML : '';
  throbberMessageElement.innerHTML = throbberMessage + ' 0%';

  const stages = ['require', 'apply', 'post_apply', 'finish'];

  const params = new URLSearchParams();
  params.append("stage", "create");
  params.append("package", composerPackage);
  const response = await sendRequest(params);
  params.append("stage_id", response.stage_id);
  for (const stage of stages) {
    params.set("stage", stage);
    const progress = Math.floor((stages.indexOf(stage) + 1) / stages.length * 100);
    throbberMessageElement.innerHTML = throbberMessage + ' ' + progress + '%';
    const result = await sendRequest(params);
    if (result.error) {
      console.error('Error during installation:', result.error);
      reloadButton.click();
      enableButtons();
      return;
    }
  }
  throbberMessageElement.innerHTML = throbberMessage + ' 100%';
  reloadButton.click();

  // throbberMessageElement.innerHTML = throbberMessage;
  // event.target.classList.remove('is-installing');
  enableButtons();
}

async function sendRequest(params) {
  try {
    const response = await fetch(`/ckeditor5-premium-features/dependency-install?${params}`);
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return await response.json();
  } catch (error) {
    console.error('Error installing package:', error);
    // Handle error response
  }
}

function disableButtons() {
  const buttons = document.querySelectorAll('.button:not(.ckeditor5-dependency-install-reload-button)');
  buttons.forEach((button) => {
    button.disabled = true;
    button.classList.add('disabled');
  });
}

function enableButtons() {
  const buttons = document.querySelectorAll('.button');
  buttons.forEach((button) => {
    button.disabled = false;
    button.classList.remove('disabled');
  });
}

