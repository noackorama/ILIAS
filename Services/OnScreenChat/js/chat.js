(function($, $scope, $io) {
	$scope.il.Chat = {
		config: {},
		socket: null,

		setConfig: function(config) {
			getModule().config = config;
		},

		init: function() {
			getModule().socket = $io.connect(getModule().config.url);
		},

		login: function(userId, username, callback) {
			getModule().socket.emit('login', getModule().config.userId, getModule().config.username);
			getModule().socket.on('login', callback);
		},

		getConversation: function(participants, callback) {
			getModule().socket.emit('conversation', participants);
			getModule().socket.on('conversation', callback)
		},

		getConversations: function() {
			getModule().socket.emit('conversations');
		},

		sendMessage: function(conversationId, message) {
			getModule().socket.emit('message', conversationId, getModule().config.userId, message);
		},

		getHistory: function(conversationId, callback) {
			getModule().socket.emit('history', conversationId);
			getModule().socket.on('history', callback)
		},

		addUser: function(conversationId, userId, callback) {
			getModule().socket.emit('addUser', conversationId, userId);
			getModule().socket.on('addUser', callback);
		},

		receiveMessage: function(callback) {
			getModule().socket.on('message', callback);
		}
	};

	function getModule() {
		return $scope.il.Chat;
	}
})(jQuery, window, io);