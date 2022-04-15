var MugShot = {
  id: 'theMainImage',

  id2: 'MugShotDiv',

  id3: 'theImage',

  imageId: false,

  postAction: false,

  lai: false,

  submitBtn: '',

  tagList: '',

  img: '',

  map: '',

  offset: {},

  mugs: [],

  cfi: -1,

  active: false,

  selecting: false,

  init: (function (f, imageId, action) {
    this.refreshImgData();
    this.makeWrapper();
    this.imageId = imageId;
    this.drawMugShots(f);
    this.createSubmitButton();
    this.assignEventListeners();
    this.postAction = action;
    this.tagList = document.getElementById('mugshot-tags');
    document.getElementById('mugshot-tags').remove();
    document.getElementById(this.id2).append(this.tagList);
    this.postAction = action;
  }),

  frame: (function () {
    if (this.active === false) {
      this.refreshImgData();
      this.toggleSubmitBtn('on');
      this.active = true;
      this.img.draggable = false;
      this.map = this.img.useMap;
      this.img.useMap = '#';
      this.img.style.cursor = 'crosshair';
      this.img.addEventListener('mousedown', beginCapture);
      window.addEventListener('keydown', reverseCapture);
    }
  }),

  makeWrapper: (function () {
    // Div for MugShot Stuff
    var parent = document.getElementById(this.id3)
    var parentStyles = document.defaultView.getComputedStyle(parent)
    var w = document.createElement('div');
    w.id = this.id2;
    w.style.left = this.offset.left - this.poffset.left + 'px';
    w.style.top = parentStyles.paddingTop;
    w.style.width = this.offset.width + 'px';
    w.style.height = '100%';
    w.style.zIndex = 1000;
    document.getElementById('theImage').append(w);
  }),

  updateWrapper: (function () {
    var parent = document.getElementById(this.id3)
    var parentStyles = document.defaultView.getComputedStyle(parent)
    var w = document.getElementById(this.id2)
    w.style.left = this.offset.left - this.poffset.left + 'px';
    w.style.top = parentStyles.paddingTop;
    w.style.width = this.offset.width + 'px';
  }),

  assignEventListeners: (function () {
    try {
      document.getElementById('navbar-contextual').addEventListener('click', refreshOnResize);
    } catch (err) {
      if (err.name == 'TypeError') {
        try {
          document.querySelector('nav').addEventListener('click', refreshOnResize);
        } catch(err) {
          if (err.name == 'TypeError') {
            document.getElementById('imageToolBar').addEventListener('click', refreshOnResize);
          } else {
            console.log(err);
          }
        }
      } else {
        console.log(err);
      }
    }
    window.addEventListener('resize', refreshOnResize);
  }),

  toggleElementSet: (function (i, x) {
    if (x == 'off') {
      if (this.mugs[i].frame.el.classList.contains('mugshot-active')) {
        this.mugs[i].frame.el.classList.toggle('mugshot-active');
      }

      if (this.mugs[i].name.el.classList.contains('mugshot-active')) {
        this.mugs[i].name.el.classList.toggle('mugshot-active');
      }

      if (this.mugs[i].accept_match.el !== '') {
        if (this.mugs[i].accept_match.el.classList.contains('mugshot-active')) {
          this.mugs[i].accept_match.el.classList.toggle('mugshot-active');
        }
      }

      if (this.mugs[i].reject_match.el !== '') {
        if (this.mugs[i].reject_match.el.classList.contains('mugshot-active')) {
          this.mugs[i].reject_match.el.classList.toggle('mugshot-active');
        }
      }

      if (this.mugs[i].remove.el.classList.contains('mugshot-active')) {
        this.mugs[i].remove.el.classList.toggle('mugshot-active');
      }

      if (this.mugs[i].face_toolbar.el.classList.contains('mugshot-active')) {
        this.mugs[i].face_toolbar.el.classList.toggle('mugshot-active');
      }
    } else if (x == 'on') {
      if (!this.mugs[i].frame.el.classList.contains('mugshot-active')) {
        this.mugs[i].frame.el.classList.toggle('mugshot-active');
      }

      if (!this.mugs[i].name.el.classList.contains('mugshot-active')) {
        this.mugs[i].name.el.classList.toggle('mugshot-active');
      }

      if (!this.mugs[i].face_toolbar.el.classList.contains('mugshot-active')) {
        this.mugs[i].face_toolbar.el.classList.toggle('mugshot-active');
      }

      if (this.mugs[i].accept_match.el !== '') {
        if (!this.mugs[i].accept_match.el.classList.contains('mugshot-active')) {
          this.mugs[i].accept_match.el.classList.toggle('mugshot-active');
        }
      }

      if (this.mugs[i].reject_match.el !== '') {
        if (!this.mugs[i].reject_match.el.classList.contains('mugshot-active')) {
          this.mugs[i].reject_match.el.classList.toggle('mugshot-active');
        }
      }

      if (!this.mugs[i].remove.el.classList.contains('mugshot-active')) {
        this.mugs[i].remove.el.classList.toggle('mugshot-active');
      }
    }
  }),

  toggleSubmitBtn: (function (x) {
    if (x == 'off') {
      if (this.submitBtn.classList.contains('mugshot-active')) {
        this.submitBtn.classList.toggle('mugshot-active');
        document.getElementById(this.id2).classList.toggle('mugshot-selecting');
        this.active = false;
      }
    } else if (x == 'on') {
      if (!this.submitBtn.classList.contains('mugshot-active')) {
        this.submitBtn.classList.toggle('mugshot-active');
        document.getElementById(this.id2).classList.toggle('mugshot-selecting');
      }
    }
  }),

  /**
   * Places mugshot elements on the page in their
   * corresponding positions
   * @param  object frames defined_mugshots
   * @return void
   */
  drawMugShots: (function (frames) {
    for (var frameIndex in frames) {
      if (frames.hasOwnProperty(frameIndex)) {
        this.createBoundingBox(frames[frameIndex]);
        this.createFaceToolbar();
        this.createTextBox();
        this.mugs[this.cfi].frame.el.ondblclick = updateBoundingBox;
        this.mugs[this.cfi].active = false;
        this.mugs[this.cfi].frame.el.classList.toggle('mugshot-mousetrap');
      }
    }

    this.refreshCapture();
  }),

  /**
   * Create a frame for the mugshot
   * @param  object frame frame object from defined_mugshots
   * @return void
   */
  createBoundingBox: (function (input_frame) {
    this.cfi += 1;
    var id = 'frame_' + this.cfi;
    var box = document.createElement('div');
    box.title = id;
    box.id = id;
    box.className = 'mugshot-frame mugshot-mousetrap mugshot-' + (parseInt(input_frame.confirmed) ? 'confirmed' : 'unconfirmed');
    box.style.top = input_frame.top + 'px';
    box.style.left = input_frame.lft + 'px';
    box.style.height = input_frame.height + 'px';
    box.style.width = input_frame.width + 'px';
    var nameEl = document.createElement('a');
    nameEl.className = 'mugshot-frame-name';
    nameEl.href = input_frame.tag_url;
    if (input_frame.tagId != null && input_frame.name != null && input_frame.name.length > 0) {
      nameEl.innerHTML = input_frame.name;
    }
    box.append(nameEl);
    document.getElementById(this.id2).append(box);

    this.mugs[this.cfi] = {
      imageId: this.imageId,
      active: true,
      frame: {
        el: box,
        id: id,
        faceId: input_frame.face_id,
        name: input_frame.name,
        prevName: input_frame.name,
        faceIndex: input_frame.face_index,
        tagId: input_frame.tag_id,
        top: input_frame.top,
        left: input_frame.lft,
        height: input_frame.height,
        width: input_frame.width,
        imageWidth: (input_frame.image_width > 0) ? input_frame.image_width : this.img.width,
        imageHeight: (input_frame.image_height > 0) ? input_frame.image_height : this.img.height,
        confirmed: input_frame.confirmed,
        prevConfirmed: input_frame.confirmed,
        removeThis: 0,
      },
      name: {
        el: '',
        id: 'name_' + this.cfi,
        left: 0,
        top: 0,
      },
      face_toolbar: {
        el: '',
        id: 'face_toolbar_' + this.cfi,
      },
      accept_match: {
        el: '',
        id: 'accept_match_' + this.cfi,
      },
      reject_match: {
        el: '',
        id: 'reject_match_' + this.cfi,
      },
      remove: {
        el: '',
        id: 'remove_' + this.cfi,
      },
    };
  }),

  setBoundingBoxPosition: (function (x, y) {
    var t = this.mugs[this.cfi].frame.top;
    var l = this.mugs[this.cfi].frame.left;
    var top = (t < y) ? t : y;
    var left = (l < x) ? l : x;
    var height = Math.abs(y - t);
    var width = Math.abs(x - l);
    return [left, top, height, width];
  }),

  setText: (function (e) {
    var el = document.getElementById(MugShot.lai);
    var i = parseInt(el.id.replace('name_', ''));
    el.value = e.innerHTML;
    e.parentNode.style.display = 'none';
    MugShot.toggleElementSet(i, 'off');
    MugShot.mugs[i].frame.name = el.value;
    MugShot.mugs[i].frame.el.title = el.value;
    MugShot.mugs[i].frame.confirmed = true;
    MugShot.mugs[i].frame.el.classList.replace('mugshot-unconfirmed', 'mugshot-confirmed');
    MugShot.mugs[i].active = false;
  }),

  refreshBoundingBoxPosition: (function (left, top, height, width, index) {
    index = (index !== false) ? index : this.cfi;
    this.mugs[index].frame.el.style.top = top + 'px';
    this.mugs[index].frame.el.style.left = left + 'px';
    this.mugs[index].frame.el.style.height = height + 'px';
    this.mugs[index].frame.el.style.width = width + 'px';
  }),

  refreshTagListPosition: (function (index) {
    if (this.lai !== false) {
      index = (index === true) ? parseInt(this.lai.replace('name_', '')) : index;
      var o = this.mugs[index].name.el.getBoundingClientRect();
      var t = o.height + o.top - MugShot.offset.top;
      this.tagList.style.left = this.mugs[index].name.el.style.left;
      this.tagList.style.width = o.width + 'px';
      this.tagList.style.top = t + 'px';
    }
  }),

  refreshCapture: (function () {
    if (this.cfi !== -1) {
      var len = this.mugs.length;

      for (var i = 0; i < len; i++) {
        var scaleX = this.img.width / this.mugs[i].frame.imageWidth;
        var scaleY = this.img.height / this.mugs[i].frame.imageHeight;

        if (scaleX !== 1) {
          var mug = this.mugs[i].frame;
          var left = Math.floor(parseInt(mug.left) * scaleX);
          var top = Math.floor(parseInt(mug.top) * scaleY);
          var width = Math.floor(parseInt(mug.width) * scaleX);
          var height = Math.floor(parseInt(mug.height) * scaleY);
          this.refreshBoundingBoxPosition(left, top, height, width, i);
          this.mugs[i].name.el.style.left = this.mugs[i].frame.el.style.left;
          this.mugs[i].name.el.style.top = top + height + 'px';
        }
      }
    }
  }),

  refreshImgData: (function () {
    this.img = document.getElementById(this.id);
    this.offset = this.img.getBoundingClientRect();
    this.poffset = document.getElementById(this.id3).getBoundingClientRect();
  }),

  createTextBox: (function () {
    var mug = this.mugs[this.cfi].frame;
    var name = document.createElement('input');
    name.addEventListener('keyup', doneWithText);
    name.id = this.mugs[this.cfi].name.id;
    name.value = mug.name != null ? mug.name : '';
    name.className = 'mugshot-textbox';
    name.style.top = parseInt(mug.top) + parseInt(mug.height) + 'px';
    name.style.left = mug.el.style.left;
    name.style.width = mug.el.style.width;
    name.autocomplete = false;
    name.type = "text";
    document.getElementById(this.id2).append(name);
    this.mugs[this.cfi].name.el = name;
    this.mugs[this.cfi].frame.el.title = mug.name != null ? mug.name : 'Unidentified Person';
  }),

  createFaceToolbar: (function () {
    var toolbar = document.createElement('div');

    toolbar.className = 'mugshot-face-toolbar';
    toolbar.title = 'Face Toolbar';
    toolbar.id = 'face_toolbar_' + this.cfi;
    this.mugs[this.cfi].face_toolbar.el = toolbar;
    if (!this.mugs[this.cfi].frame.confirmed && this.mugs[this.cfi].frame.tagId != null &&
        this.mugs[this.cfi].frame.tagId > 0) {
      this.createAcceptMatchButton();
      this.createRejectMatchButton();
    }
    this.createDeleteButton();
    this.mugs[this.cfi].frame.el.append(toolbar);
  }),

  createAcceptMatchButton: (function () {
    var btn = document.createElement('span');
    btn.className = 'mugshot-accept-match mugshot-icon mugshot-icon-thumbsup';
    btn.title = 'Accept Match';
    btn.id = 'accept_match_' + this.cfi;
    btn.onclick = this.acceptMatch.bind(this);
    this.mugs[this.cfi].accept_match.el = btn;
    this.mugs[this.cfi].face_toolbar.el.append(btn);
  }),

  createRejectMatchButton: (function () {
    var btn = document.createElement('span');
    btn.className = 'mugshot-reject-match mugshot-icon mugshot-icon-thumbsdown';
    btn.title = 'Reject Match';
    btn.id = 'reject_match_' + this.cfi;
    btn.onclick = this.rejectMatch.bind(this);
    this.mugs[this.cfi].reject_match.el = btn;
    this.mugs[this.cfi].face_toolbar.el.append(btn);
  }),

  createDeleteButton: (function () {
    var btn = document.createElement('span');
    btn.className = 'mugshot-delete mugshot-icon mugshot-icon-trashcan';
    btn.title = 'Delete Tag';
    btn.id = 'remove_' + this.cfi;
    btn.onclick = this.deleteMugShot.bind(this);
    this.mugs[this.cfi].remove.el = btn;
    this.mugs[this.cfi].face_toolbar.el.append(btn);
  }),

  createSubmitButton: (function () {
    var btn = document.createElement('button');
    btn.className = 'mugshot-done-button';
    btn.id = 'mugShotSubmit';
    btn.style.left = '0px';
    btn.style.bottom = '0px';
    btn.onclick = this.submitMugShots.bind(this);
    this.submitBtn = btn;
    document.getElementById(this.id2).append(btn);
  }),

  submitMugShots: (function () {
    var data = {};

    this.toggleSubmitBtn('off');
    this.tagList.style.display = 'none';

    if (this.mugs.length > 0) {

      data.imageId = this.mugs[0].imageId;

      for (var i = 0; i < this.mugs.length; i++) {
        data['mug_' + i] = this.mugs[i].frame;

        this.mugs[i].active = false;
        this.toggleElementSet(i, 'off');
      }

      this.sendToServer(data);
    }
  }),

  urlEncodeData: (function (obj, prefix) {
    var str = [];
    var p;
    var k;

    for (p in obj) {
      if (obj.hasOwnProperty(p)) {
        k = prefix ? prefix + '[' + p + ']' : p, v = obj[p];
        str.push((v !== null && typeof v == 'object') ?
          this.urlEncodeData(v, k) :
          encodeURIComponent(k) + '=' + encodeURIComponent(v));
      }
    }

    return str.join('&');
  }),

  sendToServer: (function (data) {
    this.xhr = new XMLHttpRequest();
    this.xhr.onload = this.parseFromServer;
    this.xhr.open('POST', this.postAction, true);
    this.xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    this.xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    this.xhr.responseType = 'json';
//    let request_data = JSON.stringify(data);
//    let request_wrapper = [];
//    request_wrapper['data'] = request_data;
    let request_params = this.urlEncodeData([data=>JSON.stringify(data)]); // request_wrapper);
    this.xhr.send(request_params);
  }),

  parseFromServer: (function (e) {
    if (e.target.status === 200) {

      MugShot.resetMugShot();

      if (e.target.response) {
        console.log(JSON.parse(e.target.response.result));
      }
    } else {
      console.log('Error: Unsuccessfully updated Database');
    }
    location.reload();
  }),

  resetMugShot: (function () {
    this.img.useMap = this.map;
    this.img.draggable = true;
    this.img.style.cursor = 'auto';
    this.img.removeEventListener('mousedown', beginCapture);
    document.removeEventListener('keydown', reverseCapture);
  }),

  acceptMatch: (function (e) {
    var index = parseInt(e.target.id.replace('accept_match_', ''));
    this.mugs[index].accept_match.el.remove();
    this.mugs[index].reject_match.el.remove();
    this.mugs[index].frame.confirmed = true;
    this.mugs[index].frame.el.classList.replace('mugshot-unconfirmed', 'mugshot-confirmed');
  }),

  rejectMatch: (function (e) {
    var index = parseInt(e.target.id.replace('reject_match_', ''));
    this.mugs[index].accept_match.el.remove();
    this.mugs[index].reject_match.el.remove();
    this.mugs[index].frame.name = null;
    this.mugs[index].frame.el.title = "Unidentified Person";
  }),

  deleteMugShot: (function (e) {
    var index = parseInt(e.target.id.replace('remove_', ''));
    this.mugs[index].remove.el.remove();
    if (this.mugs[index].accept_match.el !== '') {
      this.mugs[index].accept_match.el.remove();
    }
    if (this.mugs[index].reject_match.el !== '') {
      this.mugs[index].reject_match.el.remove();
    }
    if (this.mugs[index].face_toolbar.el !== '') {
      this.mugs[index].face_toolbar.el.remove();
    }
    this.mugs[index].name.el.remove();
    this.mugs[index].frame.el.remove();
    this.mugs[index].frame.removeThis = 1;
    this.toggleElementSet(index, 'off');
    this.toggleSubmitBtn('on');
    this.tagList.style.display = 'none';
  }),
};

/*
 * Event Listener functions.
 * Listed here for easier removal
 */
function beginCapture(e) {
  if (e.which === 1) {
    MugShot.selecting = true;
    MugShot.img.addEventListener('mousemove', updateCapture);
    MugShot.img.addEventListener('mouseup', haltCapture);

    // left top height width
    var frame = {
      'lft': e.pageX - MugShot.offset.left,
      'top': e.pageY - MugShot.offset.top,
      'height': 5,
      'width': 5,
      'confirmed': true
    };

    MugShot.createBoundingBox(frame);

    // Hide all the frames while we select the new one. Keeps them from interfering.
    for (let index = 0; index < MugShot.mugs.length - 1; index++) {
      MugShot.mugs[index].frame.el.classList.toggle('mugshot-hidden-while-selecting');
      MugShot.mugs[index].name.el.classList.toggle('mugshot-hidden-while-selecting');
    }

    MugShot.mugs[MugShot.cfi].frame.el.classList.toggle('mugshot-active');

    MugShot.toggleSubmitBtn('on');
  }
}

function updateCapture(e) {
  if (MugShot.selecting) {
    var pos = MugShot.setBoundingBoxPosition(e.pageX - MugShot.offset.left, e.pageY - MugShot.offset.top);
    MugShot.refreshBoundingBoxPosition(pos[0], pos[1], pos[2], pos[3], false);
  }
}

function haltCapture(e) {
  MugShot.selecting = false;
  MugShot.img.removeEventListener('mousemove', updateCapture, false);
  MugShot.img.removeEventListener('mouseup', haltCapture, false);
  MugShot.mugs[MugShot.cfi].frame.el.ondblclick = updateBoundingBox;
  MugShot.mugs[MugShot.cfi].frame.el.classList.toggle('mugshot-mousetrap');

  // Done capturing so re-show the other mugshot frames
  for (let index = 0; index < MugShot.mugs.length - 1; index++) {
    MugShot.mugs[index].frame.el.classList.toggle('mugshot-hidden-while-selecting');
    MugShot.mugs[index].name.el.classList.toggle('mugshot-hidden-while-selecting');
  }

  var pos = MugShot.setBoundingBoxPosition(e.pageX - MugShot.offset.left, e.pageY - MugShot.offset.top);
  MugShot.mugs[MugShot.cfi].frame.left = pos[0];
  MugShot.mugs[MugShot.cfi].frame.top = pos[1];
  MugShot.mugs[MugShot.cfi].frame.height = pos[2];
  MugShot.mugs[MugShot.cfi].frame.width = pos[3];
  MugShot.mugs[MugShot.cfi].name.top = pos[1] + pos[2];
  MugShot.mugs[MugShot.cfi].name.left = pos[0];
  MugShot.createFaceToolbar();
  MugShot.createTextBox();
  MugShot.toggleElementSet(MugShot.cfi, 'on');
  MugShot.mugs[MugShot.cfi].name.el.focus();
}

function updateBoundingBox(e) {
  var index = parseInt(e.target.id.replace('frame_', ''));

  if (!MugShot.mugs[index].active) {
    MugShot.toggleSubmitBtn('on');
    MugShot.toggleElementSet(index, 'on');
    MugShot.mugs[index].active = true;
    MugShot.mugs[index].name.el.focus();
  }
}

function reverseCapture(e) {
  if (e.keyCode === 90 && e.ctrlKey && MugShot.cfi > -1) {
    MugShot.deleteMugShot();
  }
}

function doneWithText(e) {
  var index = parseInt(e.target.id.replace('name_', ''));
  MugShot.mugs[index].frame.name = e.target.value;
  MugShot.mugs[index].frame.el.title = e.target.value;

  if (e.keyCode === 13) {
    MugShot.toggleElementSet(index, 'off');
    var vis = MugShot.tagList.querySelectorAll('.mugshot-tag-list-show');
    var v = (vis.length === 1) ? vis[0].innerHTML : e.target.value;
    MugShot.mugs[index].frame.name = v;
    MugShot.mugs[index].frame.el.title = v;
    MugShot.mugs[index].frame.confirmed = true;
    MugShot.mugs[index].frame.el.classList.replace('mugshot-unconfirmed', 'mugshot-confirmed');

    MugShot.mugs[index].active = false;
    MugShot.tagList.style.display = 'none';
  } else {
    var filter = e.target.value.toUpperCase();
    var list = MugShot.tagList.querySelectorAll('li');
    var i = 0;
    var j = 0;

    MugShot.lai = e.target.id;
    MugShot.refreshTagListPosition(index);

    for (i = 0; i < list.length; i++) {
      if (list[i].innerHTML.toUpperCase().indexOf(filter) > -1) {
        list[i].className = 'mugshot-tag-list-show';
        j += 1;
      } else {
        list[i].className = '';
      }
    }

    MugShot.tagList.style.display = (j < 10 && j !== 0) ? 'block' : 'none';
  }
}

function refreshOnResize(e) {

  if (e.type == 'click') {
    setTimeout(function () {
      MugShot.refreshImgData();
      MugShot.updateWrapper();
      MugShot.refreshCapture();
    }, 250);
  } else {
      MugShot.refreshImgData();
      MugShot.updateWrapper();
      MugShot.refreshCapture();
  }
}
