var MugShot = {
  id: 'theMainImage',

  img: '',

  map: '',

  offset: {},

  mugs: [],

  cfi: -1,

  init: (function (f) {
    this.refreshImgData();
    var derivatives = document.querySelectorAll('[id^="derivative"]');
    [].forEach.call(derivatives, refreshOnResize);
    this.drawMugShots(f);
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
      }
    }

    this.refreshCapture();
    window.addEventListener('resize', refreshOnResize);
    window.addEventListener('scroll', refreshOnResize);
  }),

  /**
   * Create a frame for the mugshot
   * @param  object frame frame object from defined_mugshots
   * @return void
   */
  createBoundingBox: (function (frame) {
    this.cfi += 1;
    var id = 'frame_' + this.cfi;
    var box = document.createElement('div');
    box.title = frame.name;
    box.id = id;
    box.className = 'mugshot-frame mugshot-mousetrap mugshot-' + (frame.confirmed == '1' ? 'confirmed' : 'unconfirmed');
    box.style.top = frame.top + 'px';
    box.style.left = frame.lft + 'px';
    box.style.height = frame.height + 'px';
    box.style.width = frame.width + 'px';
    if (frame.name && frame.tagId != "-1") {
      var nameEl = document.createElement('a');
      nameEl.className = 'mugshot-frame-name';
      nameEl.href = frame.tag_url;
      nameEl.innerHTML = frame.name;
      box.append(nameEl);
    }
    this.img.parentNode.append(box);
    this.mugs[this.cfi] = {
      frame: {
        el: box,
        id: id,
        name: (frame.name) ? frame.name : '',
        prevName: (frame.name) ? frame.name : '',
        top: frame.top,
        left: frame.lft,
        height: frame.height,
        width: frame.width,
        imageWidth: (frame.image_width) ? frame.image_width : this.img.width,
        imageHeight: (frame.image_height) ? frame.image_height : this.img.height,
        tagId: (frame.tag_id) ? frame.tag_id : -1,
        confirmed: frame.confirmed,
        prevConfirmed: frame.confirmed,
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
    var el = document.getElementById(MugShot.lastActiveInput);
    var i = parseInt(el.id.replace('name_', ''));
    el.value = e.innerHTML;
    e.parentNode.style.display = 'none';
    MugShot.toggleElementSet(i, 'off');
    MugShot.mugs[i].frame.name = el.value;
    MugShot.mugs[i].frame.el.title = el.value;
    MugShot.mugs[i].active = false;
  }),

  refreshBoundingBoxPosition: (function (left, top, height, width, index) {
    index = (index !== false) ? index : this.cfi;
    this.mugs[index].frame.el.style.top = top + 'px';
    this.mugs[index].frame.el.style.left = left  + 'px';
    this.mugs[index].frame.el.style.height = height + 'px';
    this.mugs[index].frame.el.style.width = width + 'px';
  }),

  refreshCapture: (function () {
    if (this.cfi !== -1) {

      var len = this.mugs.length;

      for (var i = 0; i < len; i++) {
        var scaleX = this.img.width / this.mugs[i].frame.imageWidth;
        var scaleY = this.img.height / this.mugs[i].frame.imageHeight;

        if (scaleX != 1) {
          var mug = this.mugs[i].frame;
          var left = Math.floor(this.img.offsetLeft + parseInt(mug.left) * scaleX);
          var top = Math.floor(this.img.offsetTop + parseInt(mug.top) * scaleY);
          var width = Math.floor(parseInt(mug.width) * scaleX);
          var height = Math.floor(parseInt(mug.height) * scaleY);
          this.refreshBoundingBoxPosition(left, top, height, width, i);
        }
      }
    }
  }),

  refreshImgData: (function () {
    this.img = document.getElementById(this.id);
    this.offset = this.img.getBoundingClientRect();
  }),
};

/*
 * Event Listener functions.
 * Listed here for easier removal
 */
function refreshOnResize(e) {
  if (e.type == 'resize') {
    MugShot.refreshCapture();
  } else if (e.type == 'scroll') {
    MugShot.refreshImgData();
    MugShot.refreshCapture();
  } else {
    MugShot.img.onload = function () {
      MugShot.refreshImgData();
      MugShot.refreshCapture();
    };
  }
}
