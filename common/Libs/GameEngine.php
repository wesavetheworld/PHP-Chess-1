<?php

namespace Libs;

class GameEngine
{
	/**
	 *
	 * @var ChessGame
	 */
	private $chessGame;
	
	/**
	 * If set to true (should NOT be the case unless in development stage) allows
	 * player (developer) to bypass all limits/rules
	 * 
	 * Default: false
	 * 
	 * @var bool
	 */
	private $godMode;
	
	public function __construct(ChessGame $chessGame)
	{
		$this->setChessGame($chessGame);
		
		
	}
	
	/**
	 *
	 * @return ChessGame
	 */
	public function getChessGame()
	{
		return $this->chessGame;
	}

	/**
	 *
	 * @param ChessGame $chessGame 
	 */
	protected function setChessGame(ChessGame $chessGame)
	{
		$this->chessGame = $chessGame;
	}
	
	public function isKingUnderCheckMate(ChessBoardSquare $chessBoardSquare)
	{
		if ( ! $chessBoardSquare->getChessPiece() || ! $chessBoardSquare->getChessPiece()->getType() == \Enums\ChessPieceType::KING)
		{
			return false;
		}
		
		$uci = new UCI();
		
		if ( ! $uci->getBestMove(\Libs\GameExporter::getAlgebraicNotation($this->getChessGame())))
		{
			\d("Engine reports there's not best move ! King under check mate !");
			
			return true;
		}
		else
		{
			\d("Engine reports that there's some possible movement. Not a check mate");
			
			return false;
		}
		
	}
	
	
	public function isMovementAllowed(ChessBoardSquare $fromChessBoardSquare, ChessBoardSquare $toChessBoardSquare, &$specialMovement = null)
	{	
		
		// Empty squares aren't allowed to be moved ;)
		if ( ! $fromChessBoardSquare->getChessPiece())
		{
			return false;
		}
		
		// Everything is allowed in God mode !
		if ($this->isGodMode())
		{
			return true;
		}
		
		if ($this->getChessGame()->isGameFinished())
		{
			return false;
		}
		
		// It's definitely useful to have the square where our King is located
		$ourKing	= $this->getChessGame()->getChessBoard()->findChessPiece(new ChessPiece(\Enums\ChessPieceType::KING, $this->getPlayerWhoseTurnIsNow()->getColor()));
		
		
		// Moving king under field that is under check by opponent is prohibited
		if ($fromChessBoardSquare->getChessPiece()->getType() == \Enums\ChessPieceType::KING)
		{
			// Small hack :-) 
			// We're first going to remove the king from the table so we can get
			// ABSOLUTELY all fields that are under attak (including the ones
			// that are around the king)
			
			$kingPiece	= $fromChessBoardSquare->getChessPiece(); // Save the king ;)
			$fromChessBoardSquare->setChessPiece(null); // Remove the king
			
			$isUnderAttack	= $this->isSquareUnderAttack($toChessBoardSquare);
			
			$fromChessBoardSquare->setChessPiece($kingPiece); // Return the king back ;)
			
			if ($isUnderAttack)
			{
//				echo "NO KING UNDER FIELD UNDER CHECK ! <br/>";
				return false;
			}
		}
		
		// If current player's king is under check, ONLY king can move, and ONLY
		// to fields that aren't under check !
		
		$playerKing = $ourKing;
		$tryingToEatAttacker	= false;
		
		if ($this->isSquareUnderAttack($playerKing) && ! $fromChessBoardSquare->getChessPiece()->equal($playerKing->getChessPiece()))
		{
			// What if we're trying to eat the piece attacking our king ?
			// If we do and there's no one else attacking our king - we're good to go ;)
			
			$kingAttackers	= $this->getSquareAttackers($ourKing, $this->getOpponentColor());
			
			
			if (count($kingAttackers) > 1) // More than one attacker on our king ... You have to move king definitely !
			{
//				echo "YOUR KING IS UNDER CHECK ! You have to move king first !!! (More than one attacker)<br/>";
				return false;
			}
			else // Only one attacker on our king ... If we eat it - we're good
			{
//				echo "Only one attacker, good to hear that";
				if ( ! $toChessBoardSquare->isContainedIn($kingAttackers)) // Blah ... You're not trying to eat attacker ....
				{
					// Are you trying to block the attacker from attacking our king ? :-)
					
					$toChessBoardSquare->setChessPiece($fromChessBoardSquare->getChessPiece());
					$kingStillUnderAttack = $this->isSquareUnderAttack($playerKing);
					
					$fromChessBoardSquare->setChessPiece($toChessBoardSquare->getChessPiece());
					$toChessBoardSquare->setChessPiece(null);
					
					if ($kingStillUnderAttack)
					{
//						echo "YOUR KING IS UNDER CHECK ! You have to move king first !!! <br/>";
						return false;
					}
					else
					{
						$tryingToEatAttacker	= true;
					}
					
				}
				else
				{
					$tryingToEatAttacker = true;
				}
			}
		}
		
		// Check whose turn is it ? If it's white's turn, but we tried playing 
		// with black piece (or vice - versa) -- that is NOT allowed !
		if ($this->getPlayerWhoseTurnIsNow()->getColor() != $fromChessBoardSquare->getChessPiece()->getColor())
		{
//			echo "INVALID PLAYER COLOR !!! <br/>";
			
			return false;
		}
		
		// If moving the selected piece will cause our king to be under check -
		// no way dude !
		
		$_selected_piece	= $fromChessBoardSquare->getChessPiece();
		$_destination_piece	= $toChessBoardSquare->getChessPiece();
		
		$fromChessBoardSquare->setChessPiece(null);
		$toChessBoardSquare->setChessPiece($_selected_piece);
		
		if ($_selected_piece->getType() != \Enums\ChessPieceType::KING
				&& count($this->getSquareAttackers($ourKing, $this->getOpponentColor())) > 0 
				&& $tryingToEatAttacker == false)
		{
			echo "Moving this piece will cause our king to be under check ! NO FUCKING WAY ! <br/>";
			
			$fromChessBoardSquare->setChessPiece($_selected_piece);
			
			return false;
		}
		
		$fromChessBoardSquare->setChessPiece($_selected_piece);
		$toChessBoardSquare->setChessPiece($_destination_piece);
		
		
		//
		// Was this a castling request ?
		//
		
		$castlingRequest	= false;
		
		if ($fromChessBoardSquare->getChessPiece()->getType() == \Enums\ChessPieceType::KING
				&& $toChessBoardSquare->getChessPiece()
				&& $toChessBoardSquare->getChessPiece()->getType() == \Enums\ChessPieceType::ROOK
				&& $fromChessBoardSquare->getChessPiece()->getTotalMoves() == 0
				&& $toChessBoardSquare->getChessPiece()->getTotalMoves() == 0)
		{
			
			$castlingRequest	= true;
			
			// Make sure there are no chess pieces between the selected rook
			// and king
			
			$columnRange	= range(
					$fromChessBoardSquare->getLocation()->getColumn()
					, $toChessBoardSquare->getLocation()->getColumn()
			);
			
			// Exclude the start and end position (that is - king and rook positions)
			// as we want to check only positions between them
			
			array_shift($columnRange);
			array_pop($columnRange);
			
			foreach ($columnRange AS $column)
			{
				$location = new Coordinates($fromChessBoardSquare->getLocation()->getRow(), $column);
				
				$square	= $this->getChessGame()->getChessBoard()->getSquareByLocation($location);
				
				if ($square->getChessPiece())
				{
					$castlingRequest	= false; // Nope, castling is not allowed ;)
					break;
				}
				
				if ($this->isSquareUnderAttack($square))
				{
					$castlingRequest	= false; // Sorry, no castling through fields under attack
				}
			}
			
			if ($this->isSquareUnderAttack($toChessBoardSquare))
			{
				$castlingRequest	= false;
			}
		}
		
		if ($castlingRequest)
		{
			$specialMovement	= \Enums\SpecialMovement::CASTLING;
			
			return true;
		}
		
		
		// This is more than simple operation -- get all possible movements and
		// see if requested one is one of them :-)
		
		$allowedMovements = $this->getAllPossibleMovements($fromChessBoardSquare);
		
		if (empty($allowedMovements) || ! $toChessBoardSquare->isContainedIn($allowedMovements))
		{
			return false;
		}
		else
		{	
			//
			// Check for promotion moves
			//

			$promotionRow	= ($this->getPlayerWhoseTurnIsNow()->getColor() == \Enums\Color::WHITE)
					? 8
					: 1
			;

			if ($fromChessBoardSquare->getChessPiece()->getType() == \Enums\ChessPieceType::PAWN
					&& $toChessBoardSquare->getLocation()->getRow() == $promotionRow)
			{
//				echo "PROMOTE ME BITCH :-D <br/>";
				$specialMovement	= \Enums\SpecialMovement::PROMOTION;
			}
			
			return true;
		}
	}

	
	
	
	/**
	 * Returns array of all ChessBoardSquare objects where piece from target
	 * square can move to
	 * 
	 * If there are no possible movements, empty array will be returned
	 * 
	 * @param ChessBoardSquare $chessBoardSquare 
	 * @return array|ChessBoardSquare
	 */
	public function getAllPossibleMovements(ChessBoardSquare $chessBoardSquare)
	{
		$movements = array();
		
		// If there's no chess piece - no movements are available :-)
		if ( ! $chessBoardSquare || ! $chessBoardSquare->getChessPiece())
		{
			return $movements;
		}
		
		// Delegates would come in handy now heh ....
		switch($chessBoardSquare->getChessPiece()->getType())
		{
			case \Enums\ChessPieceType::BISHOP:
				$movements	= $this->getAllPossibleMovementsForBishop($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::KING:
				$movements	= $this->getAllPossibleMovementsForKing($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::KNIGHT:
				$movements	= $this->getAllPossibleMovementsForKnight($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::PAWN:
				$movements	= $this->getAllPossibleMovementsForPawn($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::QUEEN:
				$movements	= $this->getAllPossibleMovementsForQueen($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::ROOK:
				$movements	= $this->getAllPossibleMovementsForRook($chessBoardSquare);
				break;
		}
		
		return $movements;
	}
	
	/**
	 *
	 * @param ChessBoardSquare $chessBoardSquare 
	 * @return array|ChessBoardSquare
	 */
	public function getAllSquaresUnderAttackByChessPiece(ChessBoardSquare $chessBoardSquare)
	{
		$squares = array();
		
		switch($chessBoardSquare->getChessPiece()->getType())
		{
			case \Enums\ChessPieceType::BISHOP:
				$squares	= $this->getAllPossibleMovementsForBishop($chessBoardSquare, true);
				break;
			
			case \Enums\ChessPieceType::KING:
				$squares	= $this->getAllPossibleMovementsForKing($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::KNIGHT:
				$squares	= $this->getAllPossibleMovementsForKnight($chessBoardSquare, true);
				break;
			
			case \Enums\ChessPieceType::PAWN:
				$squares	= $this->getAllSquaresUnderAttackByPawn($chessBoardSquare);
				break;
			
			case \Enums\ChessPieceType::QUEEN:
				$squares	= $this->getAllPossibleMovementsForQueen($chessBoardSquare, true);
				break;
			
			case \Enums\ChessPieceType::ROOK:
				$squares	= $this->getAllPossibleMovementsForRook($chessBoardSquare, true);
				break;
		}
		
		return $squares;
		
		
	}
	
	/**
	 * Returns the array of pieces attacking the specified square
	 * 
	 * @param ChessBoardSquare $chessBoardSquare 
	 * @param enum $colorFilter Specify if you want to get only attackers of specified color
	 * @return array|ChessBoardSquare
	 */
	public function getSquareAttackers(ChessBoardSquare $chessBoardSquare, $colorFilter = false)
	{
		$return	= array();
		
		foreach ($this->getChessGame()->getChessBoard()->getAllChessPieces($colorFilter) AS $chessPiece)
		{
			if ($chessBoardSquare->isContainedIn($this->getAllSquaresUnderAttackByChessPiece($chessPiece)))
			{
				$return[]	= $chessPiece;
			}
		}
		
		return $return;
	}
	
	/**
	 * Returns the array of ALL possible ChessBoardSquare's where KING can move
	 * from his current position.
	 * 
	 * @param ChessBoardSquare $chessBoardSquare
	 * @return array|ChessBoardSquare
	 * @todo Implement Castling movement
	 */
	public function getAllPossibleMovementsForKing(ChessBoardSquare $chessBoardSquare)
	{
		$movements = array();
		
		$movements = ChessBoardHelper::getAllNeighbourFields($this->getChessGame()->getChessBoard(), $chessBoardSquare);
		
		// TODO: Add Castling to possible movements (if allowed)
		//
		// Castling is allowed only if the following conditions are fully met:
		// 
		//		- Neither of the pieces involved in castling may have been previously moved during the game.
		//		- There must be no pieces between the king and the rook.
		//		- The king may not currently be in check, nor may the king pass 
		//			through squares that are under attack by enemy pieces, 
		//			nor move to a square where it is in check.
		
		
		
		//
		// Remove invalid movements
		//
		
		$this->removeOutOfRangeMovements($movements);
		
		//
		// Find the location where Opponent's king is located. All locations (squares)
		// around opponent's king are forbidden locations !
		//
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		$opponentKingSquare	= $this->getChessGame()->getChessBoard()->findChessPiece(new ChessPiece(\Enums\ChessPieceType::KING, $opponnentColor));
		
		// All fields around Opponent's king are NO-NO !
		// Note for myself: I just made a tweak which can cause KING to be removed
		// from table temporarily (this is the case ONLY when checking if king
		// is/will be under checck !), so sometimes this shit can be FALSE actually
		// 
		// In case it's FALSE - there are no forbidden fields for our king 
		
		if ($opponentKingSquare)
		{
			$forbiddenFields	= ChessBoardHelper::getAllNeighbourFields($this->getChessGame()->getChessBoard(), $opponentKingSquare);
		}
		else
		{
			$forbiddenFields	= array();
		}
		
		$_movements = $movements;
		
		foreach ($_movements AS $key => $destinationField)
		{
			
			// If there is chess piece of the same color on the destination field
			// movement isn't possible !
			
			if ($destinationField->getChessPiece() && $destinationField->getChessPiece()->getColor() == $this->getPlayerWhoseTurnIsNow()->getColor())
			{
				unset($movements[$key]);
				
				continue;
			}
			
			// All fields around Opponent's king are NO-NO !
			if ($destinationField->isContainedIn($forbiddenFields))
			{
				unset($movements[$key]);
				
				continue;
			}
		}
		
		
		return $movements;
	}
	
	/**
	 *
	 * @param ChessBoardSquare $chessBoardSquare
	 * @return type 
	 */
	public function getAllPossibleMovementsForQueen(ChessBoardSquare $chessBoardSquare, $attack = false)
	{
		// Queen moves can move in all horizontal, vertical and diagonal directions
		
		$movements	= array();
		
		$movement_lists = array(
			ChessBoardHelper::getAllDiagonalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare)
			, ChessBoardHelper::getAllVerticalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare)
			, ChessBoardHelper::getAllHorizontalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare)
		);
		
		$movement_lists	= \__::flatten($movement_lists);
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		

		
		// Iterate through movement lists and remove invalid movements
		foreach ($movement_lists AS $key => $linkedList)
		{
			foreach ($linkedList AS $listNode)
			{
				if ( ! $listNode || ! $listNode->data)
				{
					break;
				}
				
				/* @var $destinationField ChessBoardSquare */
				$destinationField = $listNode->data;
				
				// In case that on destination field, we have some chessPiece already:
				//
				//	a) If that's our chessPiece, this movement and all further movements in that direction are forbidden
				//	b) If that's opponent's chessPiece  -- movement is possible, but further ones aren't 
				//	c) If that's opponent's king, and $attack = false -- that one and all further are forbidden
				if (($chessPiece = $destinationField->getChessPiece()))
				{
					if ($chessPiece->getColor() == $opponnentColor)
					{
						if ($chessPiece->getType() == \Enums\ChessPieceType::KING && ! $attack)
						{
							break;
						}
						else
						{
							// Movement is possible, but all further aren't
							$movements[]	= $destinationField;
							
							break;
						}
						
					}
					else
					{
						// This seems to be our piece. If in attack, this field
						// is an "possible" move (actually, this field is protected
						// by our piece ;))
						
						if ($attack)
						{
							$movements[]	= $destinationField;
						}
						
						break;
					}
				}
				else 
				{
					$movements[]	= $destinationField;
				}
				
			}
		}
		
		
		return $movements;
		
	}
	
	/**
	 *
	 * @todo Implement El-passant move (if I ever figure out how to ...)
	 * @param ChessBoardSquare $chessBoardSquare 
	 */
	public function getAllPossibleMovementsForPawn(ChessBoardSquare $chessBoardSquare)
	{
		// Pawn can move:
		//	a) One field up (unless there's the piece of the same color on that field)
		//	b) One field to the left/right upper diagonal, if there's opponent's piece
		//		on those fields (unless the opponent's piece is KING, in which case,
		//		the movement is NOT possible !)
		//	c) Two fields up if it's pawn's first movement and if previous rules
		//		are respected
		
		$movements = array();
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		// Fix for black player where black player is allowed to go only in negative direction
		if ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
		{
			$sign = 1;
		}
		else
		{
			$sign = -1;
		}
		
		//
		// a) One field up
		//
		
		$destination_field	= $this->getChessGame()->getChessBoard()->getSquareByLocation(
				new Coordinates(
						$chessBoardSquare->getLocation()->getRow() + (1 * $sign)
						, $chessBoardSquare->getLocation()->getColumn()
				)
		);
		
		if ($destination_field && ! $destination_field->getChessPiece())
		{
			$movements[]	= $destination_field;
		}
		
		//
		// c) Two fields up
		//
		
		$destination_field	= $this->getChessGame()->getChessBoard()->getSquareByLocation(
				new Coordinates(
						$chessBoardSquare->getLocation()->getRow() + (2 * $sign)
						, $chessBoardSquare->getLocation()->getColumn()
				)
		);
		
		if ($destination_field && $chessBoardSquare->getChessPiece()->getTotalMoves() == 0 && ! $destination_field->getChessPiece())
		{
			if ( ! $destination_field->getChessPiece() 
					|| ($destination_field->getChessPiece()->getColor() == $opponnentColor && $destination_field->getChessPiece()->getType() != \Enums\ChessPieceType::KING))
			$movements[]	= $destination_field;
		}
		
		
		
		//
		// b) Diagonal top-left and diagonal top-right if there are opponent's pieces
		//
		
		$columnInt	= ord($chessBoardSquare->getLocation()->getColumn());
		
		// diagonal top-left
		$destination_field	= $this->getChessGame()->getChessBoard()->getSquareByLocation(
				new Coordinates(
						$chessBoardSquare->getLocation()->getRow() + (1 * $sign)
						, chr($columnInt - 1 * $sign)
				)
		);
		
		if ($destination_field && $destination_field->getChessPiece() 
				&& $destination_field->getChessPiece()->getColor() == $opponnentColor
				&& $destination_field->getChessPiece()->getType() != \Enums\ChessPieceType::KING)
		{
			$movements[]	= $destination_field;
		}
		
		
		// diagonal top-right
		$destination_field	= $this->getChessGame()->getChessBoard()->getSquareByLocation(
				new Coordinates(
						$chessBoardSquare->getLocation()->getRow() + (1 * $sign)
						, chr($columnInt + 1 * $sign)
				)
		);
		
		if ($destination_field && $destination_field->getChessPiece() 
				&& $destination_field->getChessPiece()->getColor() == $opponnentColor
				&& $destination_field->getChessPiece()->getType() != \Enums\ChessPieceType::KING)
		{
			$movements[]	= $destination_field;
		}
		
		
		return $movements;
	}
	
	/**
	 *
	 * @param ChessBoardSquare $chessBoardSquare 
	 * @return array|ChessBoardSquare
	 */
	public function getAllSquaresUnderAttackByPawn(ChessBoardSquare $chessBoardSquare)
	{
		
		$movements = array();
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		// Fix for black player where black player is allowed to go only in negative direction
		if ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
		{
			$sign = 1;
		}
		else
		{
			$sign = -1;
		}
		
		
		//
		// Pawn attacks diagonal top-left and diagonal top-right fields
		//
		
		$columnInt	= ord($chessBoardSquare->getLocation()->getColumn());
		
		// diagonal top-left
		$destination_field	= $this->getChessGame()->getChessBoard()->getSquareByLocation(
				new Coordinates(
						$chessBoardSquare->getLocation()->getRow() + (1 * $sign)
						, chr($columnInt - 1 * $sign)
				)
		);
		
		if ($destination_field)
		{
			$movements[]	= $destination_field;
		}
		
		
		// diagonal top-right
		$destination_field	= $this->getChessGame()->getChessBoard()->getSquareByLocation(
				new Coordinates(
						$chessBoardSquare->getLocation()->getRow() + (1 * $sign)
						, chr($columnInt + 1 * $sign)
				)
		);
		
		if ($destination_field)
		{
			$movements[]	= $destination_field;
		}
		
		
		return $movements;
	}
	
	public function getAllPossibleMovementsForRook(ChessBoardSquare $chessBoardSquare, $attack = false)
	{
		// Rook can move horizontally and vertically on all possible fields
		
		$movements = array();
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		$movement_lists = array(
			ChessBoardHelper::getAllVerticalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare)
			, ChessBoardHelper::getAllHorizontalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare)
		);
		
		$movement_lists	= \__::flatten($movement_lists);
		
		// Iterate through movement lists and remove invalid movements
		foreach ($movement_lists AS $key => $linkedList)
		{
			foreach ($linkedList AS $listNode)
			{
				if ( ! $listNode || ! $listNode->data)
				{
					break;
				}
				
				/* @var $destinationField ChessBoardSquare */
				$destinationField = $listNode->data;
				
				// If destination field is actually the field where we're at - skip that field
				if ($chessBoardSquare->equal($destinationField))
				{
					continue;
				}
				
				// In case that on destination field, we have some chessPiece already:
				//
				//	a) If that's our chessPiece, this movement and all further movements in that direction are forbidden
				//	b) If that's opponent's chessPiece  -- movement is possible, but further ones aren't 
				//	c) If that's opponent's king -- that one and all further are forbidden
				if (($chessPiece = $destinationField->getChessPiece()))
				{
					if ($chessPiece->getColor() == $opponnentColor)
					{
						if ($chessPiece->getType() == \Enums\ChessPieceType::KING && ! $attack)
						{
							break;
						}
						else
						{
							// Movement is possible, but all further aren't
							$movements[]	= $destinationField;
							
							break;
						}
						
					}
					else
					{
						// This seems to be our piece. If in attack, this field
						// is an "possible" move (actually, this field is protected
						// by our piece ;))
						
						if ($attack)
						{
							$movements[]	= $destinationField;
						}
						
						break;
					}
				}
				else 
				{
					$movements[]	= $destinationField;
				}
				
			}
		}
		
		return $movements;
	}
	
	
	
	public function getAllPossibleMovementsForBishop(ChessBoardSquare $chessBoardSquare, $attack = false)
	{
		// Rook can move diagonally on all possible fields
		
		$movements = array();
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		$movement_lists = array(
			ChessBoardHelper::getAllDiagonalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare)
		);
		
		$movement_lists	= \__::flatten($movement_lists);
		
		// Iterate through movement lists and remove invalid movements
		foreach ($movement_lists AS $key => $linkedList)
		{
			foreach ($linkedList AS $listNode)
			{
				if ( ! $listNode || ! $listNode->data)
				{
					break;
				}
				
				/* @var $destinationField ChessBoardSquare */
				$destinationField = $listNode->data;
				
				// In case that on destination field, we have some chessPiece already:
				//
				//	a) If that's our chessPiece, this movement and all further movements in that direction are forbidden
				//	b) If that's opponent's chessPiece  -- movement is possible, but further ones aren't 
				//	c) If that's opponent's king -- that one and all further are forbidden
				if (($chessPiece = $destinationField->getChessPiece()))
				{
					if ($chessPiece->getColor() == $opponnentColor)
					{
						if ($chessPiece->getType() == \Enums\ChessPieceType::KING && ! $attack)
						{
							break;
						}
						else
						{
							// Movement is possible, but all further aren't
							$movements[]	= $destinationField;
							
							break;
						}
						
					}
					else
					{
						// This seems to be our piece. If in attack, this field
						// is an "possible" move (actually, this field is protected
						// by our piece ;))
						
						if ($attack)
						{
							$movements[]	= $destinationField;
						}
						
						break;
					}
				}
				else 
				{
					$movements[]	= $destinationField;
				}
				
			}
		}
		
		return $movements;
	}
	
	
	public function getAllPossibleMovementsForKnight(ChessBoardSquare $chessBoardSquare, $attack = false)
	{
		// Knight can move as follows:
		// a) Two fields left/right
		//	a.1) Then, one field up/down
		// b) Two fields up/down
		//	b.1) Then, one field left/right
		
		$movements = array();
		
		$opponnentColor	= ($chessBoardSquare->getChessPiece()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		// Get all horizontal directions (this would be two arrays - left and right progressions)
		$_horizontal_progressions	= ChessBoardHelper::getAllHorizontalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare);
		
		$horizontal_movements	= array();
		
		foreach ($_horizontal_progressions AS $progression)
		{
			if ($progression)
			{
				$horizontal_movements[]	= $progression->readNode(2);
			}
		}
		
		$_movements_a = array();
		
		foreach ($horizontal_movements AS $horizontal_movement)
		{
			if ( ! $horizontal_movement) // Sometimes, we go out of range :-)
			{
				continue;
			}
			else
			{
				$_movements_a[]	= array(
					$this->getChessGame()->getChessBoard()->getSquareByLocation(new Coordinates(
							$horizontal_movement->getLocation()->getRow() + 1
							, $horizontal_movement->getLocation()->getColumn()
						)
					)
					, $this->getChessGame()->getChessBoard()->getSquareByLocation(new Coordinates(
							$horizontal_movement->getLocation()->getRow() - 1
							, $horizontal_movement->getLocation()->getColumn()
						)
					)
				);
			}
			
		}
		
		$movements[]	= $_movements_a;
		
		
		
		
		
		
		
		
		//
		// Get all vertial directions (this would be two arrays - top and bottom progressions)
		//
		
		$_vertical_progressions	= ChessBoardHelper::getAllVerticalFields($this->getChessGame()->getChessBoard(), $chessBoardSquare);
		
		$vertical_movements	= array();
		
		foreach ($_vertical_progressions AS $progression)
		{
			if ($progression)
			{
				$vertical_movements[]	= $progression->readNode(2);
			}
		}
		
		/* @var $vertical_movements ChessBoardSquare */
		
		$columnInt	= ord($chessBoardSquare->getLocation()->getColumn());
		
		$_movements_b = array();
		
		foreach ($vertical_movements AS $vertical_movement)
		{
			if ( ! $vertical_movement) // Sometimes, we go out of range :-)
			{
				continue;
			}
			else
			{
				$_movements_b[]	= array(
					$this->getChessGame()->getChessBoard()->getSquareByLocation(new Coordinates(
							$vertical_movement->getLocation()->getRow()
							, chr($columnInt + 1)
						)
					)
					, $this->getChessGame()->getChessBoard()->getSquareByLocation(new Coordinates(
							$vertical_movement->getLocation()->getRow()
							, chr($columnInt - 1)
						)
					)
				);
			}
			
		}
		
		$movements[]	= $_movements_b;
		
		
		
		
		
		$movement_list	= \__::flatten($movements);
		$movements = array();
		
		// Convert each to movement to single linked list
		foreach ($movement_list AS $key => $movement)
		{
			$list = new \LinkList();
			$list->insertLast($movement);
			$movement_list[$key] = $list;
		}
		
		
		// Iterate through movement lists and remove invalid movements
		foreach ($movement_list AS $key => $linkedList)
		{
			foreach ($linkedList AS $listNode)
			{
				if ( ! $listNode || ! $listNode->data)
				{
					break;
				}
				
				/* @var $destinationField ChessBoardSquare */
				$destinationField = $listNode->data;
				
				// In case that on destination field, we have some chessPiece already:
				//
				//	a) If that's our chessPiece, this movement and all further movements in that direction are forbidden
				//	b) If that's opponent's chessPiece  -- movement is possible, but further ones aren't 
				//	c) If that's opponent's king -- that one and all further are forbidden (unless we're in attack !!!)
				if (($chessPiece = $destinationField->getChessPiece()))
				{
					if ($chessPiece->getColor() == $opponnentColor)
					{
						if ($chessPiece->getType() == \Enums\ChessPieceType::KING && $attack !== true)
						{
							break;
						}
						else
						{
							// Movement is possible, but all further aren't
							$movements[]	= $destinationField;
							
							break;
						}
						
					}
					else
					{
						// This seems to be our piece. If in attack, this field
						// is an "possible" move (actually, this field is protected
						// by our piece ;))
						
						if ($attack)
						{
							$movements[]	= $destinationField;
						}
						
						break;
					}
				}
				else 
				{
					$movements[]	= $destinationField;
				}
				
			}
		}
		
		return $movements;
	}
	
	
	public function removeOutOfRangeMovements(&$movements)
	{
		$_movements = $movements;
		
		foreach ($_movements AS $key => $destinationField)
		{
			// Remove out-of-range movements
			if ( ! $destinationField)
			{
				unset ($movements[$key]);
				
				continue;
			}
		}
	}
	
	/**
	 * Returns the player whose turn to play is now (white/black)
	 * 
	 * @return Player
	 */
	public function getPlayerWhoseTurnIsNow()
	{
		// Even turns (0,2,4,6,...) are for White Player, while odd turns (1,3,5,...)
		// are for black player
		
		$total_movements = $this->getChessGame()->getTotalMovements();
		
		return ($total_movements == 0 || $total_movements % 2 == 0) 
			? $this->getChessGame()->getWhitePlayer()
			: $this->getChessGame()->getBlackPlayer()
		;
	}
	
	/**
	 * Returns the opponent's color
	 * 
	 * @return enum BLACK / WHITE 
	 */
	public function getOpponentColor()
	{
		return ($this->getPlayerWhoseTurnIsNow()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
	}
	
	/**
	 * Checks if specified square is under attack by opponent
	 * 
	 * @param ChessBoardSquare $chessBoardSquare
	 * @return bool 
	 */
	public function isSquareUnderAttack(ChessBoardSquare $chessBoardSquare)
	{
		$opponnentColor	= ($this->getPlayerWhoseTurnIsNow()->getColor() == \Enums\Color::WHITE)
				? \Enums\Color::BLACK
				: \Enums\Color::WHITE
		;
		
		// First, collect all opponent's pieces
		$opponentsPieces = $this->getChessGame()->getChessBoard()->getAllChessPieces($opponnentColor);
		
		$fieldsUnderAttack = array();
		
		foreach ($opponentsPieces AS $piece)
		{
			$fieldsUnderAttack[]	= $this->getAllSquaresUnderAttackByChessPiece($piece);
		}
		
		$fieldsUnderAttack = \__::flatten($fieldsUnderAttack);
		
		return $chessBoardSquare->isContainedIn($fieldsUnderAttack);
	}
	
	/**
	 * God Mode, if turned ON, allows developer to bypass all limits/rules and
	 * make "impossible" moves if requested
	 * 
	 * Useful for development, but has to be turned OFF when running in production
	 * 
	 * @return bool 
	 */
	public function isGodMode()
	{
		return $this->godMode;
	}
	
	/**
	 * Turns GOD Mode on or off
	 * 
	 * @see $godMode
	 * @param type $true_or_false 
	 */
	public function setGodMode($true_or_false)
	{
		$this->godMode	= (bool) $true_or_false;
	}
	
	/**
	 * Finds all pieces that can save our King from being under check-mate
	 * 
	 * @return array|ChessBoardSquare
	 */
	public function findKingSaviors()
	{
		$allPieces	= $this->getChessGame()->getChessBoard()->getAllChessPieces($this->getPlayerWhoseTurnIsNow()->getColor());
		$ourKing	= $this->getChessGame()->getChessBoard()->findChessPiece(new ChessPiece(\Enums\ChessPieceType::KING, $this->getPlayerWhoseTurnIsNow()->getColor()));
		
		$pieces	= array();
		
		foreach ($allPieces AS $square)
		{
			if ($square->getChessPiece()->getType() == \Enums\ChessPieceType::KING)
			{
				continue;
			}
			
			$originalSquarePiece	= $square->getChessPiece();
			
			$allPossibleMovements	= \__::flatten(array(
				$this->getAllPossibleMovements($square)
				, $this->getAllSquaresUnderAttackByChessPiece($square)
			));
			
			$distinctMoves	= array();
			
			// Remove duplicate moves
			foreach ($allPossibleMovements AS $possibleMovement)
			{
				if ( ! $possibleMovement->isContainedIn($distinctMoves))
				{
					$distinctMoves[]	= $possibleMovement;
				}
			}
			
			foreach ($distinctMoves AS $movement)
			{
				// Make temp movement, check if king is still under attack, and
				// revert the movement !
				
				$movementOriginalPiece	= $movement->getChessPiece();
				$movement->setChessPiece($square->getChessPiece());
				$square->setChessPiece(null);
				
				// Is king still under attack ?
				
				$kingStillUnderAttack	= $this->isSquareUnderAttack($ourKing);
				
				// And revert the virtual move :-)
				
				$square->setChessPiece($originalSquarePiece);
				$movement->setChessPiece($movementOriginalPiece);
				
				if ( ! $kingStillUnderAttack)
				{
					// Fuck yeah ! We can protect our king !
					echo "Found king savior :-) From: $square; To: $movement <br/>";
					
					$pieces[]	= $square;
				}
				
				
			}
		}
		
		return $pieces;
	}

}
