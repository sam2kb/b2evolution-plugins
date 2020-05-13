(function (j, m, n, k) {
  var a = j[k.k] = {
    w: j,
    d: m,
    n: n,
    a: k,
    s: {},
    f: function () {
      return {
        callback: [],
        kill: function (b) {
          if(typeof b === "string") b = a.d.getElementById(b);
          b && b.parentNode && b.parentNode.removeChild(b)
        },
        get: function (b, c) {
          var d = null;
          return d = typeof b[c] === "string" ? b[c] : b.getAttribute(c)
        },
        set: function (b, c, d) {
          if(typeof b[c] === "string") b[c] = d;
          else b.setAttribute(c, d)
        },
        make: function (b) {
          var c = false,
            d, e;
          for(d in b) if(b[d].hasOwnProperty) {
            c = a.d.createElement(d);
            for(e in b[d]) b[d][e].hasOwnProperty && typeof b[d][e] === "string" && a.f.set(c, e, b[d][e]);
            break
          }
          return c
        },
        listen: function (b, c, d) {
          if(typeof a.w.addEventListener !== "undefined") b.addEventListener(c, d, false);
          else typeof a.w.attachEvent !== "undefined" && b.attachEvent("on" + c, d)
        },
        getSelection: function () {
          return("" + (a.w.getSelection ? a.w.getSelection() : a.d.getSelection ? a.d.getSelection() : a.d.selection.createRange().text)).replace(/(^\s+|\s+$)/g, "")
        },
        pin: function (b) {
          var c = a.a.pin + "?",
            d = "false",
            e = a.f.get(b, "pinImg"),
            g = a.f.get(b, "pinUrl") || a.d.URL,
            f = a.v.selectedText || a.f.get(b, "pinDesc") || a.d.title;
          if(b.rel === "video") d = "true";
          c = c + "media=" + encodeURIComponent(e);
          c = c + "&url=" + encodeURIComponent(g);
          c = c + "&title=" + encodeURIComponent(a.d.title.substr(0, 500));
          c = c + "&is_video=" + d;
          c = c + "&description=" + encodeURIComponent(f.substr(0, 500));
          if(a.v.inlineHandler) c = c + "&via=" + encodeURIComponent(a.d.URL);
          if(a.v.hazIOS) {
            a.w.setTimeout(function () {
              a.w.location = "pinit12://" + c
            }, 25);
            a.w.location = "http://" + c
          } else a.w.open("http://" + c, "pin" + (new Date).getTime(), a.a.pop)
        },
        close: function (b) {
          if(a.s.bg) {
            a.d.b.removeChild(a.s.shim);
            a.d.b.removeChild(a.s.bg);
            a.d.b.removeChild(a.s.bd)
          }
          j.hazPinningNow = false;
          b && a.w.alert(b);
          a.v.hazGoodUrl = false;
          a.w.scroll(0, a.v.saveScrollTop)
        },
        click: function (b) {
          b = b || a.w.event;
          var c = null;
          if(c = b.target ? b.target.nodeType === 3 ? b.target.parentNode : b.target : b.srcElement) if(c === a.s.x) a.f.close();
          else if(c.className !== a.a.k + "_hideMe") if(c.className === a.a.k + "_pinThis") {
            a.f.pin(c);
            a.w.setTimeout(function () {
              a.f.close()
            }, 10)
          }
        },
        keydown: function (b) {
          ((b || a.w.event).keyCode || null) === 27 && a.f.close()
        },
        behavior: function () {
          a.f.listen(a.s.bd, "click", a.f.click);
          a.f.listen(a.d, "keydown", a.f.keydown)
        },
        presentation: function () {
          var b = a.f.make({
            STYLE: {
              type: "text/css"
            }
          }),
            c = a.a.cdn[a.w.location.protocol] || a.a.cdn["http:"],
            d = a.a.rules.join("\n");
          d = d.replace(/#_/g, "#" + k.k + "_");
          d = d.replace(/\._/g, "." + k.k + "_");
          d = d.replace(/_cdn/g, c);
          if(b.styleSheet) b.styleSheet.cssText = d;
          else b.appendChild(a.d.createTextNode(d));
          a.d.h.appendChild(b)
        },
        addThumb: function (b, c, d) {
          (d = b.getElementsByTagName(d)[0]) ? b.insertBefore(c, d) : b.appendChild(c)
        },
        thumb: function (b) {
          if(b.src) {
            if(!b.media) b.media = "image";
            var c = a.a.k + "_thumb_" + b.src,
              d = a.f.make({
                SPAN: {
                  className: a.a.k + "_pinContainer"
                }
              }),
              e = a.f.make({
                A: {
                  className: a.a.k + "_pinThis",
                  rel: b.media,
                  href: "#"
                }
              }),
              g = a.f.make({
                SPAN: {
                  className: "img"
                }
              }),
              f = new Image;
            a.f.set(f, "nopin", "nopin");
            b.page && a.f.set(e, "pinUrl", b.page);
            b.title && a.f.set(e, "pinDesc", b.title);
            b.desc && a.f.set(e, "pinDesc", b.desc);
            f.style.visibility = "hidden";
            f.onload = function () {
              var h = this.width,
                i = this.height;
              if(i === h) this.width = this.height = a.a.thumbCellSize;
              if(i > h) {
                this.width = a.a.thumbCellSize;
                this.height = a.a.thumbCellSize * (i / h);
                this.style.marginTop = 0 - (this.height - a.a.thumbCellSize) / 2 + "px"
              }
              if(i < h) {
                this.height = a.a.thumbCellSize;
                this.width = a.a.thumbCellSize * (h / i);
                this.style.marginLeft = 0 - (this.width - a.a.thumbCellSize) / 2 + "px"
              }
              this.style.visibility = ""
            };
            f.src = b.thumb ? b.thumb : b.src;
            a.f.set(e, "pinImg", b.src);
            if(b.extended) f.className = "extended";
            g.appendChild(f);
            d.appendChild(g);
            b.media !== "image" && d.appendChild(a.f.make({
              SPAN: {
                className: a.a.k + "_play"
              }
            }));
            g = a.f.make({
              CITE: {}
            });
            g.appendChild(a.f.make({
              span: {
                className: a.a.k + "_mask"
              }
            }));
            f = b.height + " x " + b.width;
            if(b.duration) {
              f = b.duration % 60;
              if(f < 10) f = "0" + f;
              f = ~~ (b.duration / 60) + ":" + f
            }
            f = a.f.make({
              span: {
                innerHTML: f
              }
            });
            if(b.provider) f.className = a.a.k + "_" + b.provider;
            g.appendChild(f);
            d.appendChild(g);
            d.appendChild(e);
            e = false;
            if(b.dupe) {
              g = 0;
              for(f = a.v.thumbed.length; g < f; g += 1) if(a.v.thumbed[g].id.indexOf(b.dupe) !== -1) {
                e = a.v.thumbed[g].id;
                break
              }
            }
            if(e !== false) if(e = a.d.getElementById(e)) {
              e.parentNode.insertBefore(d, e);
              e.parentNode.removeChild(e)
            } else b.page || b.media !== "image" ? a.f.addThumb(a.s.embedContainer, d, "SPAN") : a.f.addThumb(a.s.imgContainer, d, "SPAN");
            else {
              a.s.imgContainer.appendChild(d);
              a.v.hazAtLeastOneGoodThumb += 1
            }(b = a.d.getElementById(c)) && b.parentNode.removeChild(b);
            d.id = c;
            a.f.set(d, "domain", c.split("/")[2]);
            a.v.thumbed.push(d)
          }
        },
        call: function (b, c) {
          var d = a.f.callback.length,
            e = a.a.k + ".f.callback[" + d + "]",
            g = a.d.createElement("SCRIPT");
          a.f.callback[d] = function (f) {
            c(f, d);
            a.v.awaitingCallbacks -= 1;
            a.f.kill(e)
          };
          g.id = e;
          g.src = b + "&callback=" + e;
          g.type = "text/javascript";
          g.charset = "utf-8";
          a.v.firstScript.parentNode.insertBefore(g, a.v.firstScript);
          a.v.awaitingCallbacks += 1
        },
        ping: {
          checkDomain: function (b) {
            var c, d;
            if(b && b.disallowed_domains && b.disallowed_domains.length) {
              c = 0;
              for(d = b.disallowed_domains.length; c < d; c += 1) if(b.disallowed_domains[c] === a.w.location.host) {
                a.f.close(a.a.msg.noPin);
                return
              } else a.v.badDomain[b.disallowed_domains[c]] = true;
              c = 0;
              for(d = a.v.thumbed.length; c < d; c += 1) a.v.badDomain[a.f.get(a.v.thumbed[c], "domain")] === true && a.f.unThumb(a.v.thumbed[c].id.split("thumb_").pop())
            }
          },
          info: function (b) {
            if(b) if(b.err) a.f.unThumb(b.id);
            else if(b.reply && b.reply.img && b.reply.img.src) {
              var c = "";
              if(b.reply.page) c = b.reply.page;
              b.reply.nopin && b.reply.nopin === 1 ? a.f.unThumb(b.id) : a.f.thumb({
                provider: b.src,
                src: b.reply.img.src,
                height: b.reply.img.height,
                width: b.reply.img.width,
                media: b.reply.media,
                desc: b.reply.description,
                page: c,
                duration: b.reply.duration || 0,
                dupe: b.id
              })
            }
          }
        },
        unThumb: function (b) {
          b = a.a.k + "_thumb_" + b;
          var c = a.d.getElementById(b);
          if(c) {
            if(a.v.canonicalImage) if(a.a.k + "_thumb_" + a.v.canonicalImage === b) return;
            b = c.getElementsByTagName("A")[0];
            b.className = a.a.k + "_hideMe";
            b.innerHTML = a.a.msg.grayOut;
            a.v.hazAtLeastOneGoodThumb -= 1
          }
        },
        getExtendedInfo: function (b) {
          if(!a.v.hazCalledForInfo[b]) {
            a.v.hazCalledForInfo[b] = true;
            a.f.call(a.a.embed + b, a.f.ping.info)
          }
        },
        getId: function (b) {
          for(var c, d = b.u.split("?")[0].split("#")[0].split("/"); !c;) c = d.pop();
          if(b.r) c = parseInt(c, b.r);
          return encodeURIComponent(c)
        },
        hazUrl: {
          etsy: function () {
            var b = a.d.getElementsByTagName("META"),
              c, d, e = 0,
              g = b.length,
              f = {
                "og:type": "ogType",
                "og:url": "ogUrl",
                "og:image": "ogImg"
              },
              h = {};
            for(e = 0; e < g; e += 1) {
              c = a.f.get(b[e], "property");
              d = a.f.get(b[e], "content");
              if(c && d) if(f[c]) h[f[c]] = d
            }
            if(h.ogType && h.ogType === "etsymarketplace:item" && h.ogUrl && h.ogImg) {
              b = new Image;
              b.onload = function () {
                a.f.thumb({
                  src: this.src,
                  page: h.ogUrl,
                  title: a.d.title,
                  height: this.height,
                  width: this.width
                })
              };
              c = h.ogImg.split("_")[0];
              d = h.ogImg.split("xN")[1];
              a.v.canonicalImage = c + "_fullxfull" + d;
              b.src = a.v.canonicalImage
            }
          },
          flickr: function () {
            var b = a.d.getElementsByTagName("META"),
              c = 0,
              d = b.length;
            for(c = 0; c < d; c += 1) a.f.hazTag.meta(b[c]);
            if((b = a.d.getElementById("image-src")) && b.href) {
              c = new Image;
              c.onload = function () {
                a.f.thumb({
                  src: this.src,
                  height: this.height,
                  width: this.width,
                  extended: true
                });
                a.f.getExtendedInfo("src=flickr&id=" + encodeURIComponent(a.v.canonicalImage))
              };
              c.src = a.v.canonicalImage = b.href.split("_m.jpg")[0] + "_z.jpg"
            }
          },
          vimeo: function () {
            var b = a.f.getId({
              u: a.d.URL,
              r: 10
            });
            a.d.getElementsByTagName("DIV");
            a.d.getElementsByTagName("LI");
            a.d.getElementsByTagName("A");
            var c = "vimeo";
            if(a.d.URL.match(/^https/)) c += "_s";
            b > 1E3 && a.f.getExtendedInfo("src=" + c + "&id=" + b)
          },
          youtube: function () {
            for(var b = a.d.getElementsByTagName("META"), c = 0, d = b.length; c < d; c += 1) {
              var e = a.f.get(b[c], "property");
              if(e === "og:url") {
                a.v.canonicalUrl = a.f.get(b[c], "content");
                a.v.canonicalId = a.v.canonicalUrl.split("=")[1].split("&")[0]
              }
              if(e === "og:image") a.v.canonicalImage = a.f.get(b[c], "content")
            }
            if(a.v.canonicalImage && a.v.canonicalUrl) {
              b = new Image;
              b.onload = function () {
                a.f.thumb({
                  src: this.src,
                  height: this.height,
                  width: this.width,
                  type: "video",
                  extended: true
                });
                a.f.getExtendedInfo("src=youtube&id=" + encodeURIComponent(a.v.canonicalId))
              };
              b.src = a.v.canonicalImage
            } else {
              a.v.canonicalImage = null;
              a.v.canonicalUrl = null
            }
          },
          pinterest: function () {
            a.f.close(a.a.msg.installed)
          },
          facebook: function () {
            a.f.close(a.a.msg.privateDomain.replace(/%privateDomain%/, "Facebook"))
          },
          googleReader: function () {
            a.f.close(a.a.msg.privateDomain.replace(/%privateDomain%/, "Google Reader"))
          },
          stumbleUpon: function () {
            var b = 0,
              c = a.a.stumbleFrame.length,
              d;
            for(b = 0; b < c; b += 1) if(d = a.d.getElementById(a.a.stumbleFrame[b])) {
              a.f.close();
              if(a.w.confirm(a.a.msg.bustFrame)) a.w.location = d.src;
              break
            }
          },
          googleImages: function () {
            a.v.inlineHandler = "google"
          },
          tumblr: function () {
            a.v.inlineHandler = "tumblr"
          },
          netflix: function () {
            a.v.inlineHandler = "netflix"
          }
        },
        hazSite: {
          flickr: {
            img: function (b) {
              if(b.src) {
                b.src = b.src.split("?")[0];
                a.f.getExtendedInfo("src=flickr&id=" + encodeURIComponent(b.src))
              }
            }
          },
          behance: {
            img: function (b) {
              if(b.src) {
                b.src = b.src.split("?")[0];
                a.f.getExtendedInfo("src=behance&id=" + encodeURIComponent(b.src))
              }
            }
          },
          netflix: {
            img: function (b) {
              if(b = b.src.split("?")[0].split("#")[0].split("/").pop()) {
                id = parseInt(b.split(".")[0]);
                id > 1E3 && a.v.inlineHandler && a.v.inlineHandler === "netflix" && a.f.getExtendedInfo("src=netflix&id=" + id)
              }
            }
          },
          youtube: {
            img: function (b) {
              b = b.src.split("?")[0].split("#")[0].split("/");
              b.pop();
              (id = b.pop()) && a.f.getExtendedInfo("src=youtube&id=" + id)
            },
            iframe: function (b) {
              (b = a.f.getId({
                u: b.src
              })) && a.f.getExtendedInfo("src=youtube&id=" + b)
            },
            video: function (b) {
              (b = a.f.get(b, "data-youtube-id")) && a.f.getExtendedInfo("src=youtube&id=" + b)
            },
            embed: function (b) {
              var c = a.f.get(b, "flashvars"),
                d = "";
              if(c) {
                if(d = c.split("video_id=")[1]) d = d.split("&")[0];
                d = encodeURIComponent(d)
              } else d = a.f.getId({
                u: b.src
              });
              d && a.f.getExtendedInfo("src=youtube&id=" + d)
            },
            object: function (b) {
              b = a.f.get(b, "data");
              var c = "";
              if(b)(c = a.f.getId({
                u: b
              })) && a.f.getExtendedInfo("src=youtube&id=" + c)
            }
          },
          vimeo: {
            iframe: function (b) {
              b = a.f.getId({
                u: b.src,
                r: 10
              });
              b > 1E3 && a.f.getExtendedInfo("src=vimeo&id=" + b)
            }
          }
        },
        parse: function (b, c) {
          b = b.split(c);
          if(b[1]) return b[1].split("&")[0];
          return ""
        },
        handleInline: {
          google: function (b) {
            if(b) {
              var c, d = 0,
                e = 0,
                g = a.f.get(b, "src");
              if(g) {
                e = b.parentNode;
                if(e.tagName === "A" && e.href) {
                  b = a.f.parse(e.href, "&imgrefurl=");
                  c = a.f.parse(e.href, "&imgurl=");
                  d = parseInt(a.f.parse(e.href, "&w="));
                  e = parseInt(a.f.parse(e.href, "&h="));
                  c && g && b && e > a.a.minImgSize && d > a.a.minImgSize && a.f.thumb({
                    thumb: g,
                    src: c,
                    page: b,
                    height: e,
                    width: d
                  });
                  a.v.checkThisDomain[c.split("/")[2]] = true
                }
              }
            }
          },
          tumblr: function (b) {
            var c = [];
            c = null;
            c = "";
            if(b.src) {
              for(c = b.parentNode; c.tagName !== "LI" && c !== a.d.b;) c = c.parentNode;
              if(c.tagName === "LI" && c.parentNode.id === "posts") {
                c = c.getElementsByTagName("A");
                (c = c[c.length - 1]) && c.href && a.f.thumb({
                  src: b.src,
                  page: c.href,
                  height: b.height,
                  width: b.width
                })
              }
            }
          }
        },
        hazTag: {
          img: function (b) {
            if(a.v.inlineHandler && typeof a.f.handleInline[a.v.inlineHandler] === "function") a.f.handleInline[a.v.inlineHandler](b);
            else if(!b.src.match(/^data/)) {
              if(b.height > a.a.minImgSize && b.width > a.a.minImgSize) a.f.thumb({
                src: b.src,
                height: b.height,
                width: b.width,
                title: b.title || b.alt
              });
              a.v.checkThisDomain[b.src.split("/")[2]] = true
            }
          },
          meta: function (b) {
            var c, d;
            if(b.name && b.name.toUpperCase() === "PINTEREST" && b.content && b.content.toUpperCase() === "NOPIN") if(d = a.f.get(b, "description")) {
              c = "The owner of the site";
              b = a.d.URL.split("/");
              if(b[2]) c = b[2];
              a.f.close(a.a.msg.noPinReason.replace(/%s%/, c) + "\n\n" + d)
            } else a.f.close(a.a.msg.noPin)
          }
        },
        checkTags: function () {
          var b, c, d, e, g, f, h, i, l;
          a.v.tag = [];
          b = 0;
          for(c = a.a.check.length; b < c; b += 1) {
            g = a.d.getElementsByTagName(a.a.check[b]);
            d = 0;
            for(e = g.length; d < e; d += 1) {
              f = g[d];
              !a.f.get(f, "nopin") && f.style.display !== "none" && f.style.visibility !== "hidden" && a.v.tag.push(f)
            }
          }
          b = 0;
          for(c = a.v.tag.length; b < c; b += 1) {
            g = a.v.tag[b];
            f = g.tagName.toLowerCase();
            if(a.a.tag[f]) for(h in a.a.tag[f]) if(a.a.tag[f][h].hasOwnProperty) {
              i = a.a.tag[f][h];
              if(l = a.f.get(g, i.att)) {
                d = 0;
                for(e = i.match.length; d < e; d += 1) l.match(i.match[d]) && a.f.hazSite[h][f](g)
              }
            }
            a.f.hazTag[f] && a.f.hazTag[f](g)
          }
          a.f.checkDomainBlacklist()
        },
        getHeight: function () {
          return Math.max(Math.max(a.d.b.scrollHeight, a.d.d.scrollHeight), Math.max(a.d.b.offsetHeight, a.d.d.offsetHeight), Math.max(a.d.b.clientHeight, a.d.d.clientHeight))
        },
        structure: function () {
          a.s.shim = a.f.make({
            IFRAME: {
              height: "100%",
              width: "100%",
              allowTransparency: true,
              id: a.a.k + "_shim"
            }
          });
          a.f.set(a.s.shim, "nopin", "nopin");
          a.d.b.appendChild(a.s.shim);
          a.s.bg = a.f.make({
            DIV: {
              id: a.a.k + "_bg"
            }
          });
          a.d.b.appendChild(a.s.bg);
          a.s.bd = a.f.make({
            DIV: {
              id: a.a.k + "_bd"
            }
          });
          a.s.bd.appendChild(a.f.make({
            DIV: {
              id: a.a.k + "_spacer"
            }
          }));
          a.s.hd = a.f.make({
            DIV: {
              id: a.a.k + "_hd"
            }
          });
          a.s.hd.appendChild(a.f.make({
            SPAN: {
              id: a.a.k + "_logo"
            }
          }));
          a.s.x = a.f.make({
            A: {
              id: a.a.k + "_x",
              innerHTML: a.a.msg.cancelTitle
            }
          });
          a.s.hd.appendChild(a.s.x);
          a.s.bd.appendChild(a.s.hd);
          a.s.embedContainer = a.f.make({
            SPAN: {
              id: a.a.k + "_embedContainer"
            }
          });
          a.s.bd.appendChild(a.s.embedContainer);
          a.s.imgContainer = a.f.make({
            SPAN: {
              id: a.a.k + "_imgContainer"
            }
          });
          a.s.bd.appendChild(a.s.imgContainer);
          a.d.b.appendChild(a.s.bd);
          var b = a.f.getHeight();
          if(a.s.bd.offsetHeight < b) {
            a.s.bd.style.height = b + "px";
            a.s.bg.style.height = b + "px";
            a.s.shim.style.height = b + "px"
          }
          a.w.scroll(0, 0)
        },
        checkUrl: function () {
          var b;
          for(b in a.a.url) if(a.a.url[b].hasOwnProperty) if(a.d.URL.match(a.a.url[b])) {
            a.f.hazUrl[b]();
            if(a.v.hazGoodUrl === false) return false
          }
          return true
        },
        checkPage: function () {
          if(a.f.checkUrl()) {
            a.v.canonicalImage || a.f.checkTags();
            if(a.v.hazGoodUrl === false) return false
          } else return false;
          return true
        },
        checkDomainBlacklist: function () {
          var b = a.a.checkDomain.url + "?domains=",
            c, d = 0;
          for(c in a.v.checkThisDomain) if(a.v.checkThisDomain[c].hasOwnProperty && !a.v.checkDomainDone[c]) {
            a.v.checkDomainDone[c] = true;
            if(d) b += ",";
            d += 1;
            b += encodeURIComponent(c);
            if(d > a.a.maxCheckCount) {
              a.f.call(b, a.f.ping.checkDomain);
              b = a.a.checkDomain.url + "?domains=";
              d = 0
            }
          }
          d > 0 && a.f.call(b, a.f.ping.checkDomain)
        },
        init: function () {
          a.d.d = a.d.documentElement;
          a.d.b = a.d.getElementsByTagName("BODY")[0];
          a.d.h = a.d.getElementsByTagName("HEAD")[0];
          if(!a.d.b || !a.d.h) a.f.close(a.a.msg.noPinIncompletePage);
          else if(j.hazPinningNow !== true) {
            j.hazPinningNow = true;
            var b = a.n.userAgent;
            a.v = {
              saveScrollTop: a.w.pageYOffset,
              hazGoodUrl: true,
              hazAtLeastOneGoodThumb: 0,
              awaitingCallbacks: 0,
              thumbed: [],
              hazIE: function () {
                return /msie/i.test(b) && !/opera/i.test(b)
              }(),
              hazIOS: function () {
                return b.match(/iP/) !== null
              }(),
              firstScript: a.d.getElementsByTagName("SCRIPT")[0],
              selectedText: a.f.getSelection(),
              hazCalledForInfo: {},
              checkThisDomain: {},
              checkDomainDone: {},
              badDomain: {}
            };
            a.v.checkThisDomain[a.w.location.host] = true;
            a.f.checkDomainBlacklist();
            a.f.presentation();
            a.f.structure();
            if(a.f.checkPage()) if(a.v.hazGoodUrl === true) {
              a.f.behavior();
              if(a.f.callback.length > 1) a.v.waitForCallbacks = a.w.setInterval(function () {
                if(a.v.awaitingCallbacks === 0) if(a.v.hazAtLeastOneGoodThumb === 0 || a.v.tag.length === 0) {
                  a.w.clearInterval(a.v.waitForCallbacks);
                  a.f.close(a.a.msg.notFound)
                }
              }, 500);
              else if(!a.v.canonicalImage && (a.v.hazAtLeastOneGoodThumb === 0 || a.v.tag.length === 0)) a.f.close(a.a.msg.notFound)
            }
          }
        }
      }
    }()
  };
  a.f.init()
})(window, document, navigator, {
  k: "PIN_" + (new Date).getTime(),
  checkDomain: {
    url: "//api.pinterest.com/v2/domains/filter_nopin/"
  },
  cdn: {
    "https:": "https://a248.e.akamai.net/passets.pinterest.com.s3.amazonaws.com",
    "http:": "http://kent-assets.pinterest.com"
  },
  embed: "//pinterest.com/embed/?",
  pin: "pinterest.com/pin/create/bookmarklet/",
  minImgSize: 80,
  maxCheckCount: 20,
  thumbCellSize: 200,
  check: ["meta", "iframe", "embed", "object", "img", "video", "a"],
  url: {
    etsy: /^https?:\/\/.*?\.etsy\.com\/listing\//,
    facebook: /^https?:\/\/.*?\.facebook\.com\//,
    flickr: /^https?:\/\/www\.flickr\.com\//,
    googleImages: /^https?:\/\/.*?\.google\.com\/search/,
    googleReader: /^https?:\/\/.*?\.google\.com\/reader\//,
    netflix: /^https?:\/\/.*?\.netflix\.com/,
    pinterest: /^https?:\/\/.*?\.?pinterest\.com\//,
    stumbleUpon: /^https?:\/\/.*?\.stumbleupon\.com\//,
    tumblr: /^https?:\/\/www\.tumblr\.com/,
    vimeo: /^https?:\/\/vimeo\.com\//,
    youtube: /^https?:\/\/www\.youtube\.com\/watch\?/
  },
  stumbleFrame: ["tb-stumble-frame", "stumbleFrame"],
  tag: {
    img: {
      flickr: {
        att: "src",
        match: [/staticflickr.com/, /static.flickr.com/]
      },
      behance: {
        att: "src",
        match: [/^http:\/\/behance\.vo\.llnwd\.net/]
      },
      netflix: {
        att: "src",
        match: [/^http:\/\/cdn-?[0-9]\.nflximg\.com/, /^http?s:\/\/netflix\.hs\.llnwd\.net/]
      },
      youtube: {
        att: "src",
        match: [/ytimg.com\/vi/, /img.youtube.com\/vi/]
      }
    },
    video: {
      youtube: {
        att: "src",
        match: [/videoplayback/]
      }
    },
    embed: {
      youtube: {
        att: "src",
        match: [/^http:\/\/s\.ytimg\.com\/yt/, /^http:\/\/.*?\.?youtube-nocookie\.com\/v/]
      }
    },
    iframe: {
      youtube: {
        att: "src",
        match: [/^http:\/\/www\.youtube\.com\/embed\/([a-zA-Z0-9\-_]+)/]
      },
      vimeo: {
        att: "src",
        match: [/^http?s:\/\/vimeo.com\/(\d+)/, /^http:\/\/player\.vimeo\.com\/video\/(\d+)/]
      }
    },
    object: {
      youtube: {
        att: "data",
        match: [/^http:\/\/.*?\.?youtube-nocookie\.com\/v/]
      }
    }
  },
  msg: {
    check: "",
    cancelTitle: "Cancel",
    grayOut: "Sorry, cannot pin this image.",
    noPinIncompletePage: "Sorry, can't pin from non-HTML pages. If you're trying to upload an image, please visit pinterest.com.",
    bustFrame: "We need to hide the StumbleUpon toolbar to Pin from this page.  After pinning, you can use the back button in your browser to return to StumbleUpon. Click OK to continue or Cancel to stay here.",
    noPin: "Sorry, pinning is not allowed from this domain. Please contact the site operator if you have any questions.",
    noPinReason: "Pinning is not allowed from this page.\n\n%s% provided the following reason:",
    privateDomain: "Sorry, can't pin directly from %privateDomain%.",
    notFound: "Sorry, couldn't find any pinnable images or video on this page.",
    installed: "The bookmarklet is installed! Now you can click your Pin It button to pin images as you browse sites around the web."
  },
  pop: "status=no,resizable=yes,scrollbars=yes,personalbar=no,directories=no,location=no,toolbar=no,menubar=no,width=632,height=270,left=0,top=0",
  rules: ["#_shim {z-index:8675309; position: absolute; background: transparent; top:0; right:0; bottom:0; left:0; width: 100%; border: 0;}", "#_bg {z-index:8675310; position: absolute; top:0; right:0; bottom:0; left:0; background-color:#f2f2f2; opacity:.95; width: 100%; }", "#_bd {z-index:8675311; position: absolute; text-align: center; width: 100%; top: 0; left: 0; right: 0; font:16px hevetica neue,arial,san-serif; }", "#_bd #_hd { z-index:8675312; -moz-box-shadow: 0 1px 2px #aaa; -webkit-box-shadow: 0 1px 2px #aaa; box-shadow: 0 1px 2px #aaa; position: fixed; *position:absolute; width:100%; top: 0; left: 0; right: 0; height: 45px; line-height: 45px; font-size: 14px; font-weight: bold; display: block; margin: 0; background: #fbf7f7; border-bottom: 1px solid #aaa; }", "#_bd #_hd a#_x { display: inline-block; cursor: pointer; color: #524D4D; text-shadow: 0 1px #fff; float: right; text-align: center; width: 100px; border-left: 1px solid #aaa; }", "#_bd #_hd a#_x:hover { color: #524D4D; background: #e1dfdf; text-decoration: none; }", "#_bd #_hd a#_x:active { color: #fff; background: #cb2027; text-decoration: none; text-shadow:none;}", "#_bd #_hd #_logo {height: 43px; width: 100px; display: inline-block; margin-right: -100px; background: transparent url(_cdn/images/LogoRed.png) 50% 50% no-repeat; border: none;}", "#_bd #_spacer { display: block; height: 50px; }", "#_bd span._pinContainer { height:200px; width:200px; display:inline-block; background:#fff; position:relative; -moz-box-shadow:0 0  2px #555; -webkit-box-shadow: 0 0  2px #555; box-shadow: 0 0  2px #555; margin: 10px; }", "#_bd span._pinContainer { zoom:1; *border: 1px solid #aaa; }", "#_bd span._pinContainer img { margin:0; padding:0; border:none; }", "#_bd span._pinContainer span.img, #_bd span._pinContainer span._play { position: absolute; top: 0; left: 0; height:200px; width:200px; overflow:hidden; }", "#_bd span._pinContainer span._play { background: transparent url(_cdn/images/bm/play.png) 50% 50% no-repeat; }", "#_bd span._pinContainer cite, #_bd span._pinContainer cite span { position: absolute; bottom: 0; left: 0; right: 0; width: 200px; color: #000; height: 22px; line-height: 24px; font-size: 10px; font-style: normal; text-align: center; overflow: hidden; }", "#_bd span._pinContainer cite span._mask { background:#eee; opacity:.75; *filter:alpha(opacity=75); }", "#_bd span._pinContainer cite span._flickr { background: transparent url(_cdn/images/attrib/flickr.png) 182px 3px no-repeat; }", "#_bd span._pinContainer cite span._vimeo { background: transparent url(_cdn/images/attrib/vimeo.png) 180px 3px no-repeat; }", "#_bd span._pinContainer cite span._youtube { background: transparent url(_cdn/images/attrib/youtube.png) 180px 3px no-repeat; }", "#_bd span._pinContainer cite span._behance { background: transparent url(_cdn/images/attrib/behance.png) 180px 3px no-repeat; }", "#_bd span._pinContainer a { text-decoration:none; background:transparent url(_cdn/images/bm/button.png) 60px 300px no-repeat; cursor:pointer; position:absolute; top:0; left:0; height:200px; width:200px; }", "#_bd span._pinContainer a { -moz-transition-property: background-color; -moz-transition-duration: .25s; -webkit-transition-property: background-color; -webkit-transition-duration: .25s; transition-property: background-color; transition-duration: .25s; }", "#_bd span._pinContainer a:hover { background-position: 60px 80px; background-color:rgba(0, 0, 0, 0.5); }", "#_bd span._pinContainer a._hideMe { background: rgba(128, 128, 128, .5); *background: #aaa; *filter:alpha(opacity=75); line-height: 200px; font-size: 10px; color: #fff; text-align: center; font-weight: normal!important; }"]
});