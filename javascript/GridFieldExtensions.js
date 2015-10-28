(function($) {
	$.entwine("ss", function($) {
		/**
		 * GridFieldOrderableRows
		 */

		$(".ss-versioned-gridfield-orderable tbody").entwine({
			onadd: function() {
				var self = this;

				var helper = function(e, row) {
					return row.clone()
					          .addClass("ss-gridfield-orderhelper")
					          .width("auto")
					          .find(".col-buttons")
					          .remove()
					          .end();
				};

				var update = function() {
					var grid = self.getGridField();

					var data = grid.getItems().map(function() {
						return { name: "order[]", value: $(this).data("id") };
					});

					grid.reload({
						url: grid.data("url-reorder"),
						data: data.get()
					});
				};

				this.sortable({
					handle: ".handle",
					helper: helper,
					opacity: .7,
					update: update
				});
			},
			onremove: function() {
				this.sortable("destroy");
			}
		});

		$(".ss-versioned-gridfield-orderable .ss-gridfield-previouspage, .ss-versioned-gridfield-orderable .ss-gridfield-nextpage").entwine({
			onadd: function() {
				var grid = this.getGridField();

				if(this.is(":disabled")) {
					return false;
				}

				var drop = function(e, ui) {
					var page;

					if($(this).hasClass("ss-gridfield-previouspage")) {
						page = "prev";
					} else {
						page = "next";
					}

					grid.find("tbody").sortable("cancel");
					grid.reload({
						url: grid.data("url-movetopage"),
						data: [
							{ name: "move[id]", value: ui.draggable.data("id") },
							{ name: "move[page]", value: page }
						]
					});
				};

				this.droppable({
					accept: ".ss-gridfield-item",
					activeClass: "ui-droppable-active ui-state-highlight",
					disabled: this.prop("disabled"),
					drop: drop,
					tolerance: "pointer"
				});
			},
			onremove: function() {
				if(this.hasClass("ui-droppable")) this.droppable("destroy");
			}
		});
	});
})(jQuery);
